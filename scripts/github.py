# -*- coding: utf-8 -*-
"""
Encar scraper (Chrome auto-translate -> English)

- Force Chrome to translate Korean -> English for every page.
- On the LIST page: parse brand/model/variant **and price** first,
  normalize price immediately (KRW -> EUR) using multiple candidates.
- Then open details to complete the rest (km, fuel, color, images, etc.).
- Scrape "Options/Features", list-row "In detail" **color/seats**,
  and capture the **View Performance Record Details** link.
- Albanian labels for fuel/transmission/colors etc.
- Unknown/Korean color -> "----".
- Price fix: robust parsing; **no uplift**; final price rounded down to nearest 10.

This version intentionally **removes all skip logic** (no lease/year filters)
and **forces the list to lazy-load all rows** so it won’t stop after ~5.
"""

from splinter import Browser
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
import time, os, json, csv, re
from webdriver_manager.chrome import ChromeDriverManager
from collections import deque
from urllib.parse import urlsplit, urlunsplit, parse_qsl, urlencode
from selenium.common.exceptions import (
    ElementNotInteractableException,
    ElementClickInterceptedException,
    StaleElementReferenceException,
    UnexpectedAlertPresentException,
    NoAlertPresentException,
)
import uuid
import pymysql
import pathlib
import tempfile, shutil, subprocess
from contextlib import contextmanager
def db_conn():
    return pymysql.connect(
        host=os.getenv("DB_HOST", "127.0.0.1"),
        port=int(os.getenv("DB_PORT", "3306")),
        user=os.getenv("DB_USERNAME"),
        password=os.getenv("DB_PASSWORD"),
        database=os.getenv("DB_DATABASE"),
        charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor,
        autocommit=True,
    )


def upsert_vehicle(row):
    with db_conn() as conn:
        with conn.cursor() as cur:
            cur.execute(UPSERT_SQL, row)
# ---------------- CONFIG ----------------
BASE_URL = "https://www.encar.com/fc/fc_carsearchlist.do?carType=for#!%7B%22action%22%3A%22(And.Year.range(201500..)._.Hidden.N._.CarType.N._.SellType.%EC%9D%BC%EB%B0%98.)%22%2C%22toggle%22%3A%7B%224%22%3A0%7D%2C%22layer%22%3A%22%22%2C%22sort%22%3A%22ModifiedDate%22%2C%22page%22%3A1%2C%22limit%22%3A20%2C%22searchKey%22%3A%22%22%2C%22loginCheck%22%3Afalse%7D"
MAX_LISTINGS = int(os.getenv("MAX_LISTINGS", "3"))
PER_PAGE     = int(os.getenv("PER_PAGE", "20"))
APP_ROOT = pathlib.Path(__file__).resolve().parent
CSV_DIR  = os.getenv("CSV_DIR", str(APP_ROOT / "out"))
os.makedirs(CSV_DIR, exist_ok=True)
CSV_NAME = "cars.csv"
WRITE_DB = os.getenv("WRITE_DB", "false").lower() in ("1", "true", "yes")

UPSERT_SQL = """
INSERT INTO vehicles
(prodhuesi, modeli, varianti, viti, cmimi_eur, kilometrazhi_km, karburanti, ngjyra,
 transmisioni, uleset, vin, engine_cc, images, listing_url, opsionet, raporti_url)
VALUES (%(prodhuesi)s, %(modeli)s, %(varianti)s, %(viti)s, %(cmimi_eur)s, %(kilometrazhi_km)s, %(karburanti)s, %(ngjyra)s,
        %(transmisioni)s, %(uleset)s, %(vin)s, %(engine_cc)s, %(images)s, %(listing_url)s, %(opsionet)s, %(raporti_url)s)
ON DUPLICATE KEY UPDATE
  cmimi_eur=VALUES(cmimi_eur),
  kilometrazhi_km=VALUES(kilometrazhi_km),
  karburanti=VALUES(karburanti),
  ngjyra=VALUES(ngjyra),
  transmisioni=VALUES(transmisioni),
  uleset=VALUES(uleset),
  engine_cc=VALUES(engine_cc),
  images=VALUES(images),
  opsionet=VALUES(opsionet),
  raporti_url=VALUES(raporti_url);
"""
# Exchange rate (KRW -> EUR). Override with:  KRW_EUR=0.00061 python ...
def getenv_float(name, default):
    v = os.getenv(name, "")
    try:
        s = (v or "").strip()
        # keep only digits, dot/comma, exp markers, sign
        s = re.sub(r"[^0-9,.\-+eE]", "", s)
        s = s.replace(",", ".")
        return float(s) if s else float(default)
    except Exception:
        return float(default)


KRW_EUR = getenv_float("KRW_EUR", 0.000615)
FINISH_WORDS_RE = re.compile(
    r'\b(metallic|metal|met|pearl|pearlcoat|pearl\-coat|pearlized|pearly|pearl effect|'
    r'matte|matt|flat|satin|gloss|glossy|solid|standard|classic|premium|effect|'
    r'coat|tri\-coat|triple coat|two\-tone|bi\-tone|dual tone)\b',
    re.I
)

# ---------------- Normalization maps (KR/EN -> AL) ----------------
FUEL_MAP = {
    "가솔린":"Benzinë","휘발유":"Benzinë","gasoline":"Benzinë",
    "디젤":"Dizel","경유":"Dizel","diesel":"Dizel",
    "하이브리드":"Hibrid","가솔린+전기":"Hibrid","디젤+전기":"Hibrid","hybrid":"Hibrid",
    "전기":"Elektrik","ev":"Elektrik","elec":"Elektrik",
    "lpg":"GPL","lp지":"GPL","lpi":"GPL",
}
TRANS_MAP = {
    "오토":"Automatik","자동":"Automatik","auto":"Automatik","automatic":"Automatik",
    "수동":"Manual","수동변속기":"Manual","manual":"Manual"
}

BODY_TYPE_MAP = {
    "suv":"SUV","crossover":"SUV","cuv":"SUV","sport utility vehicle":"SUV",
    "sedan":"Sedan","saloon":"Sedan","notchback":"Sedan","fastback":"Sedan",
    "hatchback":"Hatchback","hatch":"Hatchback","liftback":"Hatchback","back door":"Hatchback",
    "wagon":"Karavan","estate":"Karavan","touring":"Karavan","avant":"Karavan","shooting brake":"Karavan",
    "coupe":"Kupe","coupé":"Kupe","2-door":"Kupe","two door":"Kupe","grand tourer":"Kupe","gt":"Kupe",
    "convertible":"Kabrio","cabriolet":"Kabrio","roadster":"Kabrio","spyder":"Kabrio","spider":"Kabrio","targa":"Kabrio","speedster":"Kabrio",
    "minivan":"Minivan/MPV","mpv":"Minivan/MPV","van":"Minivan/MPV","passenger van":"Minivan/MPV",
    "people mover":"Minivan/MPV","people carrier":"Minivan/MPV","minibus":"Autobus","bus":"Autobus",
    "pickup":"Pickup","pick-up":"Pickup","ute":"Pickup","truck":"Pickup","crew cab":"Pickup","double cab":"Pickup","single cab":"Pickup",
    "panel van":"Minivan/MPV","cargo van":"Minivan/MPV","commercial":"Komerçiale","lcv":"Komerçiale","box truck":"Komerçiale",
    "microcar":"Hatchback","city car":"Hatchback","kei car":"Hatchback",
    "limousine":"Sedan","long wheelbase":"Sedan","sedan coupe":"Sedan",
    "sports car":"Kupe","supercar":"Kupe","hypercar":"Kupe",
    "세단":"Sedan","쿠페":"Kupe","해치백":"Hatchback","왜건":"Karavan","컨버터블":"Kabrio","로드스터":"Kabrio",
    "스파이더":"Kabrio","밴":"Minivan/MPV","승합":"Minivan/MPV","픽업":"Pickup","suv":"SUV",
    "리무진":"Sedan","버스":"Autobus",
}

COLOR_MAP = {
    "black":"E zezë","jet black":"E zezë","onyx":"E zezë","piano black":"E zezë","카본 블랙":"E zezë","블랙":"E zezë","검정":"E zezë","검정색":"E zezë",
    "white":"E bardhë","pure white":"E bardhë","polar white":"E bardhë","ivory":"E bardhë","cream":"E bardhë","pearl white":"E bardhë","화이트":"E bardhë","흰색":"E bardhë","아이보리":"E bardhë","크림":"E bardhë",
    "silver":"Argjendtë","brilliant silver":"Argjendtë","bright silver":"Argjendtë","실버":"Argjendtë","은색":"Argjendtë",
    "gray":"Gri","grey":"Gri","graphite":"Gri","gunmetal":"Gri","charcoal":"Gri","magnetic":"Gri","쥐색":"Gri","그레이":"Gri","회색":"Gri",
    "blue":"Blu","dark blue":"Blu","navy":"Blu","midnight blue":"Blu","indigo":"Blu","azure":"Blu","royal blue":"Blu","sky blue":"Blu","light blue":"Blu",
    "블루":"Blu","청색":"Blu","파랑":"Blu","남색":"Blu","하늘색":"Blu",
    "red":"E kuqe","bright red":"E kuqe","solid red":"E kuqe","crimson":"E kuqe","scarlet":"E kuqe","레드":"E kuqe","빨강":"E kuqe","버건디":"Bordo","burgundy":"Bordo","maroon":"Bordo","wine":"Bordo","와인":"Bordo",
    "green":"E gjelbër","dark green":"E gjelbër","forest green":"E gjelbër","emerald":"E gjelbër","olive":"E gjelbër","lime":"E gjelbër","mint":"E gjelbër","teal":"E gjelbër",
    "그린":"E gjelbër","초록":"E gjelbër","민트":"E gjelbër","청록":"E gjelbër","올리브":"E gjelbër",
    "yellow":"E verdhë","황색":"E verdhë","노랑":"E verdhë","옐로우":"E verdhë",
    "orange":"Portokalli","주황":"Portokalli","주황색":"Portokalli",
    "brown":"Kafe","cocoa":"Kafe","coffee":"Kafe","chocolate":"Kafe","브라운":"Kafe","갈색":"Kafe","밤색":"Kafe",
    "beige":"Bezhë","sand":"Bezhë","tan":"Bezhë","khaki":"Bezhë","샌드":"Bezhë","베이지":"Bezhë","카키":"Bezhë",
    "gold":"I artë","champagne":"Shampanjë","bronze":"Bronzi","copper":"Bakri","로즈 골드":"I artë","골드":"I artë","브론즈":"Bronzi",
    "purple":"Vjollcë","violet":"Vjollcë","lavender":"Vjollcë","보라":"Vjollcë","퍼플":"Vjollcë","자색":"Vjollcë",
    "pink":"Rozë","fuchsia":"Rozë","magenta":"Rozë","hot pink":"Rozë","핑크":"Rozë",
    "pearl":"E bardhë","pearl white":"E bardhë","진주":"E bardhë","펄":"E bardhë",
    "turquoise":"E gjelbër","aqua":"E gjelbër","cyan":"E gjelbër",
    "multicolor":"Shumëngjyrëshe","multi color":"Shumëngjyrëshe","two tone":"Shumëngjyrëshe","dual tone":"Shumëngjyrëshe",
}

REPORT_URL_RE = re.compile(r'mdsl_regcar\.do\?method=inspection(View|ImgView|ViewNew)', re.I)
REPORT_CANON_BASE = "https://www.encar.com/md/sl/mdsl_regcar.do?method=inspectionViewNew&carid="
BRAND_MAP = {
    "벤츠":"Mercedes-Benz","메르세데스":"Mercedes-Benz","아우디":"Audi","폭스바겐":"Volkswagen",
    "현대":"Hyundai","기아":"Kia","제네시스":"Genesis","볼보":"Volvo","포드":"Ford","지프":"Jeep",
    "렉서스":"Lexus","토요타":"Toyota","닛산":"Nissan","혼다":"Honda","스즈키":"Suzuki",
    "미니":"MINI","포르쉐":"Porsche","캐딜락":"Cadillac","इन피니티":"Infiniti","쉐보레":"Chevrolet",
}

# ---------------- Helpers ----------------
def is_korean(txt: str) -> bool:
    if not txt: return False
    return bool(re.search(r'[\uac00-\ud7a3]', str(txt)))

def krw_to_eur(krw: float) -> int:
    try: return int(round(float(krw) * KRW_EUR))
    except: return 0

def only_digits(text) -> int:
    if text is None: return 0
    m = re.findall(r"\d+", str(text))
    return int("".join(m)) if m else 0

def normalize_color_label(s: str) -> str:
    if not s:
        return ""
    t = s.replace('/', ' ').replace('-', ' ')
    t = FINISH_WORDS_RE.sub('', t)
    t = re.sub(r'\s+', ' ', t).strip()
    return t

def color_to_albanian(raw_color: str) -> str:
    t = normalize_color_label(raw_color or "")
    if not t:
        return "----"
    key = t.lower().strip()
    out = COLOR_MAP.get(key)
    if out:
        return out
    for tok in key.split():
        if tok in COLOR_MAP:
            return COLOR_MAP[tok]
    if re.search(r'\b(graphite|gunmetal|charcoal|magnetic|slate|titanium)\b', key):
        return "Gri"
    if re.search(r'\b(silver|argent|platinum|chrome)\b', key):
        return "Argjendtë"
    if re.search(r'(그레이|회색)', key):   # grey
        return "Gri"
    if re.search(r'(실버|은색)', key):    # silver
        return "Argjendtë"
    return "----" if is_korean(t) else t

def parse_seats(val) -> int:
    s = str(val or "")
    m = re.search(r"(\d{1,2})", s)
    return int(m.group(1)) if m else 0

def dedup(seq):
    seen=set(); out=[]
    for x in seq:
        if not x: continue
        if x not in seen:
            seen.add(x); out.append(x)
    return out

PLACEHOLDER = "Kontakto Pronarin"
def fill_blanks_in_row(row: dict):
    for k, v in list(row.items()):
        if isinstance(v, str) and not v.strip():
            row[k] = PLACEHOLDER
    return row

def round_down_to_10(n: int) -> int:
    try:
        n = int(n)
    except:
        return 0
    return (n // 10) * 10

# ======== PRICE NORMALIZATION ========
KM_NEAR_RE = re.compile(r'(?:\bkm\b|㎞|주행|mileage|연식)', re.I)

def price_text_to_krw(s: str) -> int:
    if not s: return 0
    if KM_NEAR_RE.search(s): return 0
    t = s.replace(",", "").strip().lower()

    m = re.search(r'([\d\.]+)\s*(m|million)\s*(won)\b', t, re.I)
    if m:
        try: return int(round(float(m.group(1)) * 1_000_000))
        except: return 0

    m = re.search(r'[₩￦]\s*([\d\.]+)', t)
    if m:
        try: return int(float(m.group(1).replace(".", "")))
        except: return 0

    m = re.search(r'(\d[\d\.]*)\s*(won|원)\b', t, re.I)
    if m:
        try: return int(float(m.group(1).replace(".", "")))
        except: return 0

    m = re.search(r'(\d+(?:\.\d+)?)\s*억(?:\s*(\d+(?:\.\d+)?)\s*만)?', t)
    if m:
        try:
            eok=float(m.group(1)); man=float(m.group(2) or 0)
            return int(round(eok * 100_000_000 + man * 10_000))
        except: return 0

    m = re.search(r'(\d+(?:\.\d+)?)\s*만\s*원?', t)
    if m:
        try: return int(round(float(m.group(1)) * 10_000))
        except: return 0

    return 0

def normalize_ad_price_to_krw(ad_price) -> int:
    if ad_price in (None, "", 0): return 0
    if isinstance(ad_price, str):
        s = ad_price.strip()
        if not s: return 0
        if re.search(r'[₩￦]|won|원|억|만원|만', s, re.I):
            return price_text_to_krw(s)
        try:
            v = float(s.replace(",", ""))
        except:
            return 0
    else:
        try:
            v = float(ad_price)
        except:
            return 0

    if v >= 50_000_000:
        return int(round(v))
    if v >= 10_000:
        return int(round(v))
    return 0
# ======== /PRICE NORMALIZATION ========

# ---------------- State/DOM helpers ----------------
def wait_ready(browser, timeout=10):
    t0 = time.time()
    while time.time()-t0 < timeout:
        try:
            if browser.evaluate_script("document.readyState")=="complete":
                return True
        except:
            pass
        time.sleep(0.2)
    return False

def wait_for_state(browser, timeout=12):
    t0 = time.time()
    js = r"""(function(){
      if (typeof window.__PRELOADED_STATE__ !== 'undefined') return true;
      return Array.from(document.scripts||[]).some(s => (s.text||'').indexOf('__PRELOADED_STATE__')>=0);
    })()"""
    while time.time()-t0 < timeout:
        try:
            if browser.evaluate_script(js):
                return True
        except:
            pass
        time.sleep(0.25)
    return False

def get_full_state(browser):
    try:
        s = browser.evaluate_script(
          "typeof window.__PRELOADED_STATE__!=='undefined'?JSON.stringify(window.__PRELOADED_STATE__):null"
        )
        if s:
            return json.loads(s)
    except:
        pass
    try:
        code = browser.evaluate_script(r"""
          (function(){
            var ss = Array.from(document.scripts||[]);
            for (var i=0;i<ss.length;i++){
              var t = ss[i].text||'';
              if(t.indexOf('__PRELOADED_STATE__')>=0){
                var m=t.match(/__PRELOADED_STATE__\s*=\s*(\{[\s\S]*?\});/);
                if(m&&m[1]) return m[1];
              }
            } return null;
          })()""")
        if code:
            js_obj = code[:-1] if code.endswith(";") else code
            raw = browser.evaluate_script("JSON.stringify((" + js_obj + "))")
            if raw:
                return json.loads(raw)
    except:
        pass
    return {}

def find_first_value(obj, keys):
    q = deque([obj]); seen=set()
    while q:
        cur=q.popleft()
        if id(cur) in seen: continue
        seen.add(id(cur))
        if isinstance(cur, dict):
            for k in keys:
                if k in cur and cur[k] not in (None,"",[],{},0):
                    return cur[k]
            for v in cur.values():
                if isinstance(v,(dict,list)): q.append(v)
        elif isinstance(cur, list):
            for v in cur:
                if isinstance(v,(dict,list)): q.append(v)
    return None

def normalize_body_type(s: str) -> str:
    if not s:
        return "Sedan"
    if s in ("SUV","Sedan","Hatchback","Karavan","Kupe","Kabrio","Minivan/MPV","Pickup","Komerçiale","Autobus"):
        return s
    t = re.sub(r'[/\-]', ' ', str(s)).lower()
    t = re.sub(r'\s+', ' ', t).strip()
    for k, v in BODY_TYPE_MAP.items():
        if k in t:
            return v
    for tok in t.split():
        if tok in BODY_TYPE_MAP:
            return BODY_TYPE_MAP[tok]
    return "Sedan"

def guess_bodytype_from_text(t: str) -> str:
    if not t:
        return ""
    s = re.sub(r'[/\-]', ' ', t.lower())
    s = re.sub(r'\s+', ' ', s)
    phrases = [
        "shooting brake","people mover","people carrier",
        "passenger van","sport utility vehicle","multi purpose vehicle"
    ]
    for ph in phrases:
        if ph in s:
            if ph in BODY_TYPE_MAP:
                return BODY_TYPE_MAP[ph]
            if "utility" in ph:
                return "SUV"
            if "van" in ph or "purpose" in ph or "people" in ph:
                return "Minivan/MPV"
    for k, v in BODY_TYPE_MAP.items():
        if k in s:
            return v
    if re.search(r'\b(range rover|land cruiser|x\d{1}|gl[es]|gle|glb|glc|q[34578]|sq[34578]|xc[46]0|tucson|sorento|sportage|rav4|cr\-v|cx\-[35]|compass|renegade|cherokee)\b', s, re.I):
        return "SUV"
    return ""

def body_type_from_title(title: str) -> str:
    return guess_bodytype_from_text(title or "")

# ---------------- Ensure translation ----------------
def ensure_english(browser, timeout=6):
    t0 = time.time()
    while time.time()-t0 < timeout:
        try:
            body = browser.evaluate_script("document.body?document.body.innerText.slice(0,5000):''") or ""
            if not is_korean(body):
                return True
        except:
            pass
        time.sleep(0.4)
    return False

# ---------------- ALERT-SAFE URL HELPERS ----------------
def _handle_alert_if_any(browser):
    try:
        al = browser.driver.switch_to.alert
    except NoAlertPresentException:
        return False
    try:
        al.dismiss()
    except Exception:
        try: al.accept()
        except Exception: pass
    time.sleep(0.2)
    return True

def safe_current_url(browser) -> str:
    try:
        return browser.url
    except UnexpectedAlertPresentException:
        _handle_alert_if_any(browser)
        try:
            return browser.url
        except Exception:
            return ""
    except Exception:
        return ""

# ---------- NEW: force list to load all rows ----------
def force_load_list_rows(browser, want=PER_PAGE, max_scrolls=24, pause=0.35):
    """
    Encar list is lazy-loaded. Scroll until at least `want` rows exist
    (or we run out of attempts). Also tries clicking any "more" button.
    """
    def row_count():
        try:
            return len(browser.find_by_css('tr[data-index]'))
        except:
            return 0

    # Try a 'More' button if present
    for sel in [
        'button[aria-label*="more"]',
        'button[class*="more"]',
        'a[class*="more"]',
    ]:
        try:
            if browser.is_element_present_by_css(sel, wait_time=0.5):
                el = browser.find_by_css(sel).first
                try:
                    browser.execute_script("arguments[0].scrollIntoView({block:'center'});", el._element)
                except: pass
                try: el.click()
                except:
                    try: browser.execute_script("arguments[0].click();", el._element)
                    except: pass
                time.sleep(pause)
        except:
            pass

    seen = -1
    tries = 0
    while tries < max_scrolls and row_count() < want:
        try: browser.execute_script("window.scrollBy(0, 1200);")
        except: pass
        time.sleep(pause)
        try: browser.execute_script("window.scrollBy(0, 1200);")
        except: pass
        time.sleep(pause)
        try: browser.execute_script("window.scrollTo(0, 0);")
        except: pass
        time.sleep(pause * 0.8)

        cnt = row_count()
        if cnt != seen:
            seen = cnt
            tries = 0
        else:
            tries += 1

    time.sleep(0.25)
    return row_count()

# ---------------- Images ----------------
def deep_collect_carpicture_paths(obj):
    out=[]; q=deque([obj])
    while q:
        n=q.popleft()
        if isinstance(n, dict):
            for v in n.values():
                if isinstance(v,(dict,list)): q.append(v)
                elif isinstance(v,str) and "carpicture" in v: out.append(v)
        elif isinstance(n,list):
            for v in n:
                if isinstance(v,(dict,list)): q.append(v)
                elif isinstance(v,str) and "carpicture" in v: out.append(v)
    return out

def normalize_img_urls(urls):
    norm=[]
    for u in urls:
        if not u: continue
        u = u.strip().strip('"').strip("'").split()[0]
        if not u.startswith("http"):
            u = "https://ci.encar.com" + (u if u.startswith("/") else "/"+u)
        norm.append(u)
    seen=set(); uniq=[]
    for u in norm:
        if u not in seen:
            seen.add(u); uniq.append(u)
    return uniq

def upgrade_encar_url(u, target_h=1080, prefer_original=True):
    try:
        if not u or "carpicture" not in u: return u
        p=urlsplit(u); scheme=p.scheme or "https"; netloc=p.netloc or "ci.encar.com"
        if prefer_original: return urlunsplit((scheme, netloc, p.path, "", ""))
        q=dict(parse_qsl(p.query)); q["impolicy"]=q.get("impolicy","heightRate"); q["rh"]=str(target_h)
        for k in ["cw","ch","cg","wtmk","wtmkg","wtmkw","wtmkh","t","bg"]: q.pop(k,None)
        return urlunsplit((scheme,netloc,p.path,urlencode(q, doseq=True),""))
    except: return u

def canonicalize_report_url(u: str, carid_hint: str = "") -> str:
    if not u:
        return ""
    try:
        p = urlsplit(u)
        q = dict(parse_qsl(p.query))
    except Exception:
        q = {}
    carid = q.get("carid", "")
    if not carid:
        m = re.search(r'(?:\b|[?&])carid=(\d+)', u)
        if m:
            carid = m.group(1)
    if not carid:
        carid = str(carid_hint or "").strip()
    if not carid or not re.fullmatch(r'\d{6,}', carid):
        return ""
    return f"{REPORT_CANON_BASE}{carid}"

def upgrade_list(urls, target_h=1080, prefer_original=True):
    out=[]; seen=set()
    for u in urls:
        nu=upgrade_encar_url(u, target_h, prefer_original)
        if nu and nu not in seen: seen.add(nu); out.append(nu)
    return out

def trigger_lazy_gallery(browser):
    try:
        for _ in range(14):
            browser.execute_script("window.scrollBy(0,1200)")
            time.sleep(0.18)
        browser.execute_script("window.scrollTo(0,0)"); time.sleep(0.3)
    except: pass

def dom_collect_all_imgs(browser):
    js = r"""(function(){
      function add(L,u){ if(u && u.indexOf('carpicture')>-1) L.push(u); }
      var urls=[];
      Array.from(document.querySelectorAll('img')).forEach(function(img){
        add(urls,img.getAttribute('src'));
        ['data-src','data-lazy','data-lazy-src','data-original','data-origin'].forEach(function(a){ add(urls,img.getAttribute(a)); });
        var ss=img.getAttribute('srcset'); if(ss){ ss.split(',').forEach(function(p){ add(urls,p.trim().split(' ')[0]); }); }
      });
      Array.from(document.querySelectorAll('source')).forEach(function(s){
        var ss=s.getAttribute('srcset'); if(ss){ ss.split(',').forEach(function(p){ add(urls,p.trim().split(' ')[0]); }); }
      });
      Array.from(document.querySelectorAll('[style*="carpicture"]')).forEach(function(el){
        var m=(el.getAttribute('style')||'').match(/url\(([^)]+)\)/i);
        if(m&&m[1]) add(urls, m[1].replace(/^['"]|['"]$/g,''));
      });
      Array.from(document.querySelectorAll('a[href*="carpicture"]')).forEach(function(a){
        add(urls, a.getAttribute('href'));
      });
      return JSON.stringify(urls);
    })()"""
    try:
        res = browser.evaluate_script(js)
        if isinstance(res, str):
            try:
                parsed = json.loads(res)
                return parsed if isinstance(parsed, list) else []
            except Exception:
                return []
        return res if isinstance(res, list) else []
    except Exception:
        return []

def extract_bodytype_freeform(browser):
    texts = []
    try:
        texts.append(browser.evaluate_script("document.body?document.body.innerText:''") or "")
    except: pass
    try:
        texts.append(browser.evaluate_script("document.querySelector('meta[name=\"description\"]')?.content||''") or "")
    except: pass
    try:
        texts.append(browser.evaluate_script("""
          (function(){
            var sel=['[class*="breadcrumb"]','[class*="path"]','[class*="chip"]',
                     '[class*="spec"]','[class*="summary"]','[class*="tag"]'];
            var out=[];
            sel.forEach(s=>Array.from(document.querySelectorAll(s)).forEach(n=>out.push(n.innerText||''));
            return out.join(' | ');
          })()
        """) or "")
    except: pass

    for t in texts:
        bt = guess_bodytype_from_text(t)
        if bt:
            return bt
    return ""

def build_all_images(browser, state, want_count=20):
    j = deep_collect_carpicture_paths(state) if isinstance(state, (dict, list)) else []
    trigger_lazy_gallery(browser)
    d = dom_collect_all_imgs(browser)

    if not isinstance(j, list):
        j = [j] if j else []
    if not isinstance(d, list):
        if isinstance(d, str):
            try:
                d = json.loads(d)
                if not isinstance(d, list):
                    d = []
            except Exception:
                d = []
        else:
            d = []

    urls = upgrade_list(normalize_img_urls((j or []) + (d or [])), 1080, True)
    return urls[:want_count] if want_count else urls

# ---------------- DOM fallback & seats freeform ----------------
def dom_fallback_specs(browser):
    js = r"""(function(){
      function txt(el){return (el&&el.textContent||'').trim();}
      var out={}, pairs=[];
      document.querySelectorAll('dt').forEach(function(dt){
        var dd = dt.nextElementSibling;
        if (dd && dd.tagName && dd.tagName.toLowerCase()==='dd'){
          pairs.push([txt(dt), txt(dd)]);
        }
      });
      document.querySelectorAll('li,strong,b,th,td').forEach(function(n){
        var t=txt(n).replace(/\s+/g,' '); if(!t) return;
        if(t.indexOf(':')>0){
          var a=t.split(':'); pairs.push([a[0].trim(), a.slice(1).join(':').trim()]);
        }
      });
      function setIf(k,v){ if(v && !out[k]) out[k]=v; }
      pairs.forEach(function(p){
        var k=(p[0]||'').toLowerCase(), v=p[1]||'';
        if(/fuel|연료/.test(k)) setIf('fuel', v);
        if(/color|색상/.test(k)) setIf('color', v);
        if(/transmission|변속기|기어/.test(k)) setIf('transmission', v);
        if(/mileage|주행거리|km/.test(k)) setIf('mileage', v);
        if(/vin|차대번호/.test(k)) setIf('vin', v);
        if(/year|연식/.test(k)) setIf('year', v);
        if(/model|모델/.test(k)) setIf('model', v);
        if(/grade|트림|라인/.test(k)) setIf('grade', v);
        if(/maker|brand|제조사|메이커/.test(k)) setIf('manufacturer', v);
        if(/cc|배기량/.test(k)) setIf('engine_cc', v);
        if(/body|type|차종|차형|바디|세단|해치백|왜건|쿠페|컨버터블|로드스터|스파이더|픽업|밴|suv/.test(k)){
          setIf('body_type', v);
        } else {
          if (/(?:sedan|saloon|suv|crossover|hatchback|wagon|estate|touring|avant|shooting brake|coupe|coup|convertible|cabriolet|roadster|spyder|spider|targa|mpv|minivan|van|pickup|truck|세단|해치백|왜건|쿠페|컨버터블|로드스터|스파이더|픽업|밴)/i.test(v)){
            setIf('body_type', v);
          }
        }
      });
      var priceSel = ['.pay','.price','#price','[class*="price"]','td.price','span.price','.car_price'];
      for (var i=0;i<priceSel.length;i++){
        var el = document.querySelector(priceSel[i]);
        if (el){ out.price_text = txt(el); break; }
      }
      return out;
    })()"""
    try:
        return browser.evaluate_script(js) or {}
    except Exception:
        return {}

def extract_seats_freeform(browser):
    def scan(s):
        if not s: return ""
        t = s.replace("\u00a0"," ").lower()
        pats = [
            r'(?:좌석|승차정원|승차인원|탑승정원|탑승인원|정원)\s*[:：]?\s*(\d{1,2})\s*명?',
            r'(\d{1,2})\s*인\s*승',
            r'(\d{1,2})\s*인승',
            r'\bseating\s*capacity\s*[:：]?\s*(\d{1,2})\b',
            r'\b(\d{1,2})\s*-\s*seater\b',
            r'\b(\d{1,2})\s*seaters?\b',
            r'\bseats?\s*[:：]?\s*(\d{1,2})\b',
            r'\b(\d{1,2})\s*(?:passengers|people|occupants)\b',
        ]
        for p in pats:
            m = re.search(p, t, re.I)
            if m: return m.group(1)
        return ""
    try:
        body = browser.evaluate_script("document.body?document.body.innerText:''") or ""
    except Exception:
        body = ""
    try:
        meta = browser.evaluate_script("document.querySelector('meta[name=\"description\"]')?.content||''") or ""
    except Exception:
        meta = ""
    return scan(body) or scan(meta) or ""

SEAT_KEY_RE = re.compile(r'(seat|seats|seater|승차|탑승|정원|인승|인원|좌석)', re.I)

def _parse_seat_value(v):
    if v is None: return 0
    s = str(v)
    m = re.search(r'(\d{1,2})\s*(?:명|인|석|인승|seats?|seater)', s, re.I)
    if not m: m = re.search(r'\b(\d{1,2})\b', s)
    if m:
        n = int(m.group(1))
        if 1 <= n <= 9: return n
    try:
        n = int(float(s))
        if 1 <= n <= 9: return n
    except: pass
    return 0

def deep_find_seats_in_state(obj):
    q = deque([obj]); seen = set()
    while q:
        cur = q.popleft()
        oid = id(cur)
        if oid in seen:
            continue
        seen.add(oid)
        if isinstance(cur, dict):
            for k, v in cur.items():
                if isinstance(k, str) and SEAT_KEY_RE.search(k):
                    n = _parse_seat_value(v)
                    if n: return n
                    if isinstance(v, dict):
                        for vk in ('value', 'text', 'val'):
                            if vk in v:
                                n = _parse_seat_value(v[vk])
                                if n: return n
            for v in cur.values():
                if isinstance(v, (dict, list)): q.append(v)
        elif isinstance(cur, list):
            for v in cur:
                if isinstance(v, (dict, list)): q.append(v)
    return 0

def guess_seats_from_page(browser):
    try:
        txt = (browser.evaluate_script("document.body ? document.body.innerText : ''") or "").lower()
    except Exception:
        txt = ""
    for pat in [
        r'\b(\d{1,2})\s*-\s*seater\b', r'\b(\d{1,2})\s*seaters?\b',
        r'\bseats?\s*[:：]?\s*(\d{1,2})\b', r'\b(\d{1,2})\s*(?:passengers|people|occupants)\b'
    ]:
        m = re.search(pat, txt, re.I)
        if m:
            n = int(m.group(1))
            if 1 <= n <= 9: return n
    if re.search(r'\b(7\s*seater|seven[- ]seater)\b', txt): return 7
    if re.search(r'\b(8\s*seater|eight[- ]seater)\b', txt): return 8
    if re.search(r'\b(9\s*seater|nine[- ]seater)\b', txt): return 9
    if re.search(r'\b(roadster|speedster)\b', txt): return 2
    if re.search(r'\b(convertible|cabrio)\b', txt): return 4
    if re.search(r'\b(coupe)\b', txt): return 4
    if re.search(r'\b(mpv|minivan|passenger van)\b', txt): return 7
    if re.search(r'\b(suv|wagon|estate|touring|hatchback|sedan|saloon|limousine)\b', txt): return 5
    return 0

# --------- "In detail" scraping (COLOR / SEATS only) ----------
def _get_inline_panel_html(browser, row_index):
    js = r"""
    (function(idx){
      function T(n){return (n&&n.innerText)||'';}
      var row = document.querySelector('tr[data-index="'+idx+'"]');
      if(!row) return JSON.stringify({text:"",html:""});

      var btn =
        row.querySelector('button.DetailSummary_btn_detail__msm-h') ||
        row.querySelector('button[class^="DetailSummary_btn_detail__"][data-enlog-dt-eventnamegroup="기본정보"]');

      if(!btn){
        btn = Array.from(row.querySelectorAll('button, a')).find(function(el){
          var t=(el.textContent||'').toLowerCase();
          return /in detail|detail|상세|자세히|상세보기/.test(t);
        });
      }
      if(!btn){
        btn = Array.from(document.querySelectorAll('button[class^="DetailSummary_btn_detail__"]')).find(function(el){
          var r=el.closest('tr[data-index]');
          return r && r.getAttribute('data-index')==String(idx);
        });
      }
      if(!btn) return JSON.stringify({text:"",html:""});

      try{ btn.scrollIntoView({block:'center'});}catch(e){}
      try{ btn.click(); }catch(e){ try{ btn.dispatchEvent(new MouseEvent('click',{bubbles:true})); }catch(_e){} }

      var panel=null, deadline=Date.now()+2500;
      var sels = [
        'tr[data-index="'+idx+'"] + tr.sub',
        'tr[data-index="'+idx+'"] ~ tr.sub',
        'tr[data-index="'+idx+'"] [class*="detail"]',
        'tr[data-index="'+idx+'"] [class*="spec"]',
        '[class*="layer"] [class*="cont"]',
        '[class*="popup"] [class*="cont"]',
        '.detail_layer, .info_layer, .spec_layer'
      ];
      while(Date.now()<deadline && !panel){
        for(var i=0;i<sels.length && !panel;i++){
          panel = document.querySelector(sels[i]);
        }
      }
      var text = panel ? T(panel) : '';
      var html = panel ? (panel.innerHTML||'') : '';
      return JSON.stringify({text:text, html:html});
    })(%d)
    """ % row_index
    try:
        res = browser.evaluate_script(js)
        return json.loads(res) if res else {"text":"", "html":""}
    except Exception:
        return {"text":"", "html":""}

INLINE_SEATS_RE = re.compile(r'(\d{1,2})\s*-\s*seater|\bseats?\s*[: ]*(\d{1,2})\b', re.I)

def parse_inline_detail_values(panel):
    text = panel.get("text","") or ""
    html = panel.get("html","") or ""
    out = {"color_raw":"", "seats":0}

    def clean(s):
        s = re.sub(r'<[^>]+>', ' ', s or '')
        s = re.sub(r'\s+', ' ', s).strip()
        return s

    COLOR_LABEL_RE = re.compile(r'\b(color|colour)\b|색상|외장색|바디색', re.I)
    SEAT_LABEL_RE  = re.compile(r'\b(seats?|seater|inseong)\b|좌석|승차정원|인승|정원', re.I)

    pairs = []
    pairs += re.findall(r'<dt[^>]*>(.*?)</dt>\s*<dd[^>]*>(.*?)</dd>', html, flags=re.I|re.S)
    pairs += re.findall(r'<th[^>]*>(.*?)</th>\s*<td[^>]*>(.*?)</td>', html, flags=re.I|re.S)
    pairs += re.findall(
        r'<(?:span|div)[^>]*class="[^"]*(?:tit|title)[^"]*"[^>]*>(.*?)</(?:span|div)>\s*'
        r'<(?:span|div)[^>]*class="[^"]*(?:val|desc|value)[^"]*"[^>]*>(.*?)</(?:span|div)>',
        html, flags=re.I|re.S)

    for k_raw, v_raw in pairs:
        k = clean(k_raw).lower()
        v = clean(v_raw)
        if not k or not v: continue
        if not out["color_raw"] and COLOR_LABEL_RE.search(k):
            out["color_raw"] = v
        elif not out["seats"] and SEAT_LABEL_RE.search(k):
            m = re.search(r'(\d{1,2})', v)
            if m: out["seats"] = int(m.group(1))

    if not out["color_raw"]:
        m = re.search(r'(color|colour|색상|외장색|바디색)\s*[:：]?\s*([^\n]+)', text, re.I)
        if m: out["color_raw"] = (m.group(2) or '').strip()

    if not out["seats"]:
        m = INLINE_SEATS_RE.search(text)
        if m:
            out["seats"] = int([g for g in m.groups() if g][0])

    return out

def get_inline_report_url(browser, row_index):
    _ = _get_inline_panel_html(browser, row_index)
    time.sleep(0.25)

    try:
        browser.evaluate_script(r"""
          (function(){
            if (window.__openCapInstalled) return;
            window.__openedUrls = [];
            const _open = window.open;
            window.open = function(u, n, f){
              try{
                if (u) { 
                  try { 
                    const a=document.createElement('a'); a.href=u; 
                    window.__openedUrls.push(a.href); 
                  } catch(e){ window.__openedUrls.push(String(u)); }
                }
              }catch(e){}
              return _open ? _open.apply(this, arguments) : null;
            };
            window.__openCapInstalled = true;
          })();
        """)
    except:
        pass

    def pick_first_inspection_url_from_page():
        try:
            href = browser.evaluate_script(r"""
              (function(){
                function abs(u){ try{var a=document.createElement('a'); a.href=u; return a.href;}catch(e){return u||'';} }
                var link = Array.from(document.querySelectorAll('a[href]')).find(function(el){
                  var h=(el.getAttribute('href')||'') + ' ' + (el.textContent||'');
                  return /inspectionView|report|inspection|record|성능|점검|기록부/i.test(h);
                });
                return link ? abs(link.getAttribute('href')) : '';
              })()
            """) or ""
            return href
        except:
            return ""

    direct = pick_first_inspection_url_from_page()
    if direct:
        return direct

    js_click = r"""
    (function(idx){
      function findPanel(){
        var sels = [
          'tr[data-index="'+idx+'"] + tr.sub',
          'tr[data-index="'+idx+'"] ~ tr.sub',
          'tr[data-index="'+idx+'"] [class*="detail"]',
          'tr[data-index="'+idx+'"] [class*="spec"]',
          '.detail_layer, .info_layer, .spec_layer'
        ];
        for (var i=0;i<sels.length;i++){
          var p=document.querySelector(sels[i]); if(p) return p;
        }
        return null;
      }
      var panel = findPanel();
      if(!panel) return false;
      var btn = Array.from(panel.querySelectorAll('button,a')).find(function(el){
        var t=(el.textContent||'').toLowerCase();
        var g=(el.getAttribute('data-enlog-dt-eventnamegroup')||'').toLowerCase();
        var h=(el.getAttribute('href')||'').toLowerCase();
        return /inspection|report|record|성능|점검|기록부/.test(t) ||
               /inspection|report|record/.test(h) ||
               /차량상태|성능/.test(g);
      });
      if(!btn) return false;
      try{ btn.scrollIntoView({block:'center'});}catch(e){}
      try{ btn.click(); return true; }catch(e){
        try{ btn.dispatchEvent(new MouseEvent('click',{bubbles:true})); return true; }catch(_e){}
      }
      return false;
    })(%d)
    """ % row_index

    prev_tabs = len(browser.windows)
    try:
        _clicked = browser.evaluate_script(js_click)
    except:
        _clicked = False

    t0 = time.time()
    while time.time()-t0 < 4:
        if len(browser.windows) > prev_tabs:
            try:
                browser.windows[-1].is_current = True
                time.sleep(0.6)
                url = safe_current_url(browser) or ""
                browser.windows.current.close()
                browser.windows[0].is_current = True
                if url: return url
            except UnexpectedAlertPresentException:
                _handle_alert_if_any(browser)
                try:
                    url = safe_current_url(browser) or ""
                except Exception:
                    url = ""
                try:
                    browser.windows.current.close()
                except Exception:
                    pass
                try:
                    browser.windows[0].is_current = True
                except Exception:
                    pass
                if url:
                    return url
                break
            except:
                try: browser.windows[0].is_current = True
                except: pass
                break
        time.sleep(0.2)

    try:
        opened_urls = browser.evaluate_script("JSON.stringify(window.__openedUrls||[])")
        if opened_urls:
            arr = json.loads(opened_urls) or []
            for u in arr:
                if u and (REPORT_URL_RE.search(u) or "inspectionView" in u):
                    return u
    except:
        pass

    late = pick_first_inspection_url_from_page()
    if late:
        return late

    return ""

def deep_collect_report_links_from_state(obj):
    urls=[]; q=deque([obj]); seen=set()
    while q:
        cur=q.popleft()
        if id(cur) in seen: continue
        seen.add(id(cur))
        if isinstance(cur, dict):
            for v in cur.values():
                if isinstance(v, (dict, list)):
                    q.append(v)
                elif isinstance(v, str):
                    if re.search(r'https?://', v) and re.search(r'(report|inspection|record|pdf|성능|점검|기록부)', v, re.I):
                        urls.append(v)
        elif isinstance(cur, list):
            for v in cur:
                if isinstance(v, (dict, list)):
                    q.append(v)
                elif isinstance(v, str):
                    if re.search(r'https?://', v) and re.search(r'(report|inspection|record|pdf|성능|점검|기록부)', v, re.I):
                        urls.append(v)
    return dedup(urls)

def try_click_and_grab_report(browser):
    js = r"""(function(){
      function pick(){
        const tags = Array.from(document.querySelectorAll('a,button'));
        for (const el of tags){
          const t=(el.textContent||'').trim().toLowerCase();
          if(!t) continue;
          if(/report|성능|점검|기록부|inspection|performance|record/.test(t)) return el;
        }
        return null;
      }
      const el = pick();
      if (el){
        el.scrollIntoView({block:'center'});
        el.click();
        return true;
      }
      return false;
    })()"""
    prev=len(browser.windows)
    try:
        clicked = browser.evaluate_script(js)
        if not clicked:
            return None
        t0=time.time()
        while time.time()-t0<6:
            if len(browser.windows)>prev:
                browser.windows[-1].is_current=True
                time.sleep(0.8)
                url=safe_current_url(browser)
                browser.windows.current.close()
                browser.windows[0].is_current=True
                return url
            time.sleep(0.3)
    except:
        pass
    return None

def dom_collect_options_and_report(browser):
    js=r"""(function(){
      function txt(el){return (el&&el.textContent||'').replace(/\s+/g,' ').trim();}
      function add(arr, t){
        if(!t) return;
        if(t.length<2) return;
        const low=t.toLowerCase();
        if(/^option\s*\d+$/.test(low)) return;
        if(/^(옵션|사양)\s*\d+$/.test(low)) return;
        if(arr.indexOf(t)===-1) arr.push(t);
      }
      var out={features:[], reports:[]};
      var containers = Array.from(document.querySelectorAll(
        '[class*="option"] ,[class*="feature"],[id*="option"],[id*="feature"],[class*="사양"],[class*="옵션"]'
      ));
      containers.forEach(function(sec){
        Array.from(sec.querySelectorAll('li,button,a,span,div')).forEach(function(n){
          var t = txt(n);
          if (t && t.length<=50 && !/^#/.test(t)) add(out.features, t);
        });
      });
      Array.from(document.querySelectorAll('li')).forEach(function(li){
        var t = txt(li);
        if (/(option|feature|사양|옵션)/i.test(li.className||'') || /(option|feature|사양|옵션)/i.test((li.parentElement||{}).className||'')){
          if (t && t.length<=50) add(out.features, t);
        }
      });
      var linkCandidates = Array.from(document.querySelectorAll('a[href],button'));
      linkCandidates.forEach(function(el){
        var t=txt(el).toLowerCase();
        var href = (el.getAttribute('href')||'').trim();
        if(/report|성능|점검|기록부|inspection|performance|record|pdf/.test(t) || /report|inspection|record|pdf|성능|점검|기록부/.test(href)){
          if(href && href.indexOf('javascript:')!==0){
            try{
              var a=document.createElement('a'); a.href=href;
              var abs=a.href;
              if(out.reports.indexOf(abs)===-1) out.reports.push(abs);
            }catch(e){}
          }
        }
      });
      return out;
    })()"""
    try:
        obj = browser.evaluate_script(js) or {}
    except:
        obj = {}
    features = obj.get("features", []) if isinstance(obj, dict) else []
    reports  = obj.get("reports", []) if isinstance(obj, dict) else []
    return dedup(features), dedup(reports)

# ------------- LIST PAGE: brand/model/variant/price -------------
def parse_title_brand_model_variant(title: str):
    if not title:
        return "", "", ""
    t = re.sub(r"\s+", " ", title.replace("·", " ")).strip()
    toks = t.split(" ")
    if len(toks) == 1:
        return toks[0], "", ""
    brand = toks[0]
    for k, v in BRAND_MAP.items():
        if k in brand:
            brand = v
            break
    idx = None
    for i in range(1, len(toks)):
        token = toks[i]
        if re.search(r"(xdrive|quattro|[0-9]{1,2}\.[0-9]|[a-z]\d|\d[a-z]|gti|gtd|gt|tdi|fsi|fwd|awd|4wd|sport|luxury|premium|line|m\s?\d+|amg|s\s?line)", token, re.I):
            idx = i; break
        if re.search(r"\d", token) and i > 1:
            idx = i; break
    if idx is None:
        model = " ".join(toks[1:-1]).strip() if len(toks) > 2 else toks[1]
        variant = toks[-1] if len(toks) > 2 else ""
    else:
        model = " ".join(toks[1:idx]).strip()
        variant = " ".join(toks[idx:]).strip()
    return brand.strip(), model.strip(), variant.strip()

def _coerce_float(x):
    try:
        s = str(x).strip()
        # remove everything except digits, dot, exp markers, sign
        s = re.sub(r"[^0-9.\-+eE]", "", s)
        # collapse thousands-like extra dots (e.g., 123.456.789 -> 123456789)
        parts = s.split(".")
        if len(parts) > 2:
            s = parts[0] + "." + "".join(parts[1:])  # keep first dot as decimal
            # If this still looks like an integer grouping case, fallback to removing all dots:
            if not re.match(r"^-?\d+(\.\d+)?([eE][+-]?\d+)?$", s):
                s = "".join(parts)  # no decimal; pure integer
        return float(s)
    except:
        return None

def get_list_records_from_state(state):
    results = []
    q = deque([state]); seen=set()
    while q:
        cur = q.popleft()
        if id(cur) in seen: continue
        seen.add(id(cur))
        if isinstance(cur, dict):
            for v in cur.values():
                if isinstance(v, (dict, list)):
                    q.append(v)
        elif isinstance(cur, list):
            if cur and isinstance(cur[0], dict):
                sample = cur[0]
                keys = "title name carName price salePrice listPrice link href carId carNo priceText".split()
                if any(k in sample for k in keys):
                    for i, it in enumerate(cur):
                        if not isinstance(it, dict): continue
                        title = it.get("title") or it.get("name") or it.get("carName") or ""
                        pnum = it.get("price", None)
                        if pnum is None: pnum = it.get("salePrice", None)
                        if pnum is None: pnum = it.get("listPrice", None)
                        pnum = _coerce_float(pnum)
                        ptxt = it.get("priceText") or it.get("price") or it.get("salePrice") or it.get("listPrice") or ""
                        href = it.get("link") or it.get("href") or ""
                        results.append({"idx": i, "title": str(title), "priceText": str(ptxt), "priceNum": pnum, "href": href})
    return results

def list_row_dom_extract(row):
    title = ""; priceText = ""; href = ""; html = ""
    try:
        html = row.html
    except:
        html = ""
    try:
        info = row.find_by_css("td.inf")
        if info:
            a = info.first.find_by_tag("a")
            if a:
                title = a.first.text.strip()
                try: href = a.first["href"]
                except: href = ""
            else:
                title = info.first.text.strip()
    except: pass
    try:
        for sel in ['td.prc','td.prc *','[class*=price]','strong','em','span']:
            els = row.find_by_css(sel)
            if els:
                txt = els.first.text.strip()
                if re.search(r'\d', txt):
                    priceText = txt
                    break
    except: pass
    return {"title": title, "priceText": priceText, "href": href, "priceNum": None, "row_html": html}

PRICE_CELL_RE = re.compile(
    r'(<(?:td|div|span)[^>]*(?:class|id)\s*=\s*"[^"]*(?:prc|price|pay)[^"]*"[^>]*>.*?</(?:td|div|span)>)',
    re.I | re.S
)

def extract_price_chunks_from_row(html: str):
    chunks = []
    if not html: return chunks
    for m in PRICE_CELL_RE.finditer(html):
        piece = m.group(1)
        txt = re.sub(r'<[^>]+>', ' ', piece)
        txt = re.sub(r'\s+', ' ', txt).strip()
        if txt and not KM_NEAR_RE.search(txt):
            chunks.append(txt)
    if not chunks:
        txt = re.sub(r'<[^>]+>', ' ', html or '')
        txt = re.sub(r'\s+', ' ', txt).strip()
        if txt and not KM_NEAR_RE.search(txt):
            chunks.append(txt)
    return chunks

def parse_price_candidates_from_html(html: str):
    if not html: return []
    cands = []
    for chunk in extract_price_chunks_from_row(html):
        v = price_text_to_krw(chunk)
        if v: cands.append(v)
        for m in re.finditer(r'[₩￦]\s*([\d,\.]{4,})', chunk):
            try: cands.append(int(float(m.group(1).replace(",", "").replace(".", ""))))
            except: pass
        for m in re.finditer(r'(\d[\d,\.]{2,})\s*(won|원)\b', chunk, flags=re.I):
            try: cands.append(int(float(m.group(1).replace(",", "").replace(".", ""))))
            except: pass
        m = re.search(r'(\d+(?:\.\d+)?)\s*억(?:\s*(\d+(?:\.\d+)?)\s*만)?', chunk)
        if m:
            try:
                eok=float(m.group(1)); man=float(m.group(2) or 0)
                cands.append(int(round(eok*100_000_000 + man*10_000)))
            except: pass
        for m in re.finditer(r'(\d+(?:\.\d+)?)\s*만\s*원?', chunk):
            try: cands.append(int(round(float(m.group(1))*10_000)))
            except: pass
    seen=set(); out=[]
    for v in cands:
        if v and v not in seen:
            seen.add(v); out.append(v)
    return out

def pick_best_krw(candidates):
    plausible = [c for c in candidates if 3_000_000 <= c <= 400_000_000]
    if not plausible: return 0
    from collections import Counter
    cnt = Counter(plausible).most_common()
    best = cnt[0][0]
    return max([v for v in plausible if v == best] + [best])

def parse_list_price_eur(price_text: str, price_num, row_html: str):
    cands = []
    if price_num is not None:
        v = normalize_ad_price_to_krw(price_num)
        if v: cands.append(v)
    cands += parse_price_candidates_from_html(row_html or "")
    if price_text:
        v = price_text_to_krw(price_text)
        if v: cands.append(v)
    krw = pick_best_krw(cands)
    return krw, krw_to_eur(krw)

def _extract_carid_from_state_or_url(state, url: str) -> str:
    cand = None
    q = deque([state]); seen=set()
    while q and not cand:
        cur = q.popleft()
        oid = id(cur)
        if oid in seen: continue
        seen.add(oid)
        if isinstance(cur, dict):
            for k,v in cur.items():
                lk = str(k).lower()
                if any(x in lk for x in ['carid','carno','car_id','car_no','carseq','cid']):
                    s = str(v).strip()
                    if re.fullmatch(r'\d{6,}', s): cand = s; break
            if not cand:
                for v in cur.values():
                    if isinstance(v,(dict,list)): q.append(v)
        elif isinstance(cur, list):
            for v in cur:
                if isinstance(v,(dict,list)): q.append(v)

    if not cand and url:
        try:
            qs = dict(parse_qsl(urlsplit(url).query))
            for key in ['carid','carId','carno','carNo','cid','seq','car_seq']:
                if key in qs and re.fullmatch(r'\d{6,}', qs[key]): 
                    cand = qs[key]; break
        except:
            pass
    return cand or ""

def _build_report_url_from_carid(carid: str) -> str:
    if not carid: return ""
    return f"https://www.encar.com/md/sl/mdsl_regcar.do?method=inspectionViewNew&carid={carid}"

# ------------- DETAIL PAGE: scrape raw fields -------------
def scrape_detail_raw(browser):
    wait_ready(browser, timeout=12); ensure_english(browser, 4)
    st = get_full_state(browser) if wait_for_state(browser, 6) else {}

    manufacturer = find_first_value(st, ["manufacturerName","makerName","brandName"])
    model        = find_first_value(st, ["modelName"])
    grade        = find_first_value(st, ["badgeName","grade","gradeName","trimName"])
    form_year    = find_first_value(st, ["formYear","modelYear"])
    year_month   = find_first_value(st, ["yearMonth","ym"])

    ad_price     = find_first_value(st, ["price","salePrice","listPrice"])
    mileage      = find_first_value(st, ["mileage","odo","odometer"])
    fuel         = find_first_value(st, ["fuelName","fuelTypeName","fuel"])
    color        = find_first_value(st, ["colorName","exteriorColor"])
    transmission = find_first_value(st, ["transmissionName","transmission","gearbox"])
    body_type = find_first_value(
        st, ["bodyType","vehicleType","carType","body","차종","차형","바디타입"]
    )
    seats = find_first_value(
        st, ["seatCount","seats","seatCnt","seat_cnt","riderCnt","ridePerson",
             "rideCount","rideCnt","personCnt","occupancy","capacity","승차정원","인승","좌석"]
    )
    if not seats:
        seats = deep_find_seats_in_state(st)

    vin          = find_first_value(st, ["vin","vehicleId","vinNo"])
    engine_cc    = find_first_value(st, ["displacement","engineCC","cc"])

    specs = dom_fallback_specs(browser)
    seats_free = extract_seats_freeform(browser)

    manufacturer = manufacturer or specs.get("manufacturer")
    model        = model or specs.get("model")
    grade        = grade or specs.get("grade")
    mileage      = mileage or specs.get("mileage")
    fuel         = fuel or specs.get("fuel")
    color        = color or specs.get("color")
    transmission = transmission or specs.get("transmission")
    seats = seats or specs.get("seats") or seats_free or guess_seats_from_page(browser) or 0
    vin          = vin or specs.get("vin")
    engine_cc    = engine_cc or specs.get("engine_cc")
    price_text   = specs.get("price_text", "")
    body_type    = body_type or specs.get("body_type") or extract_bodytype_freeform(browser)

    features_dom, report_dom = dom_collect_options_and_report(browser)
    report_state = deep_collect_report_links_from_state(st)
    report_click = try_click_and_grab_report(browser)

    carid = _extract_carid_from_state_or_url(st, safe_current_url(browser))
    raw_links = dedup(
        ([_build_report_url_from_carid(carid)] if carid else [])
        + report_dom
        + report_state
        + ([report_click] if report_click else [])
    )

    canon_links = []
    for u in raw_links:
        c = canonicalize_report_url(u, carid)
        if c:
            canon_links.append(c)

    if not canon_links and carid:
        canon_links = [_build_report_url_from_carid(carid)]

    report_links = dedup(canon_links)
    images = build_all_images(browser, st, want_count=20)

    return {
        "manufacturer": manufacturer, "model": model, "grade": grade,
        "form_year": form_year, "year_month": year_month,
        "ad_price": ad_price, "price_text": price_text,
        "mileage": mileage, "fuel": fuel, "color": color,
        "transmission": transmission, "seats": seats, "vin": vin,
        "engine_cc": engine_cc, "images": images,
        "body_type": body_type,
        "features": features_dom,
        "report_links": report_links,
        "carid": carid,
    }

# ------------- Merge -> Albanian schema (no 'lloji') -------------
def to_albanian_schema(raw, detail_url, list_hint):
    viti = ""
    form_year = raw.get("form_year")
    year_month = raw.get("year_month")
    if form_year:
        viti = str(form_year)
    else:
        ym = str(year_month or "")
        if re.fullmatch(r"\d{6}", ym):
            viti = ym[:4]

    km_int   = only_digits(raw.get("mileage"))
    seats_hint = list_hint.get("seats_hint") or 0
    seats_i  = parse_seats(seats_hint if seats_hint else raw.get("seats"))
    karb  = str(raw.get("fuel") or "").strip()
    trans = str(raw.get("transmission") or "").strip()
    color_source = list_hint.get("color_hint") or raw.get("color") or ""
    ngj_al = color_to_albanian(color_source)

    features_list = raw.get("features") or []
    if not isinstance(features_list, list):
        features_list = [features_list]

    karb_al  = FUEL_MAP.get(karb.lower(), karb or "")
    trans_al = TRANS_MAP.get(trans.lower(), trans or "")

    prodhuesi = list_hint.get("prodhuesi") or (raw.get("manufacturer") or "")
    modeli    = list_hint.get("modeli")    or (raw.get("model") or "")
    varianti  = list_hint.get("varianti")  or (raw.get("grade") or "")

    cm_eur = list_hint.get("cmimi_eur")
    if not cm_eur:
        krw = normalize_ad_price_to_krw(raw.get("ad_price")) or price_text_to_krw(raw.get("price_text"))
        cm_eur = krw_to_eur(krw)
    cm_eur = round_down_to_10(cm_eur or 0)

    engine_cc = only_digits(raw.get("engine_cc") or 0)
    if not engine_cc:
        engine_cc = list_hint.get("engine_cc_hint") or 0

    inline_report = list_hint.get("inline_report_url") or ""
    links = raw.get("report_links", []) or []
    if inline_report:
        links = [inline_report] + links

    carid_hint = str(raw.get("carid") or "")
    norm = []
    for u in links:
        cu = canonicalize_report_url(u, carid_hint)
        if cu:
            norm.append(cu)
    if not norm and carid_hint:
        norm = [_build_report_url_from_carid(carid_hint)]
    raporte = ";".join(dedup(norm))
    opsionet = ";".join(str(x) for x in features_list)

    raw_vin = str(raw.get("vin") or "").strip()
    vin_norm = re.sub(r'[^A-Za-z0-9]', '', raw_vin).upper()
    if len(vin_norm) < 11:
        vin_norm = "-----"

    return {
        "prodhuesi":       prodhuesi,
        "modeli":          modeli,
        "varianti":        varianti,
        "viti":            viti,
        "cmimi_eur":       cm_eur,
        "kilometrazhi_km": km_int,
        "karburanti":      karb_al,
        "ngjyra":          ngj_al,
        "transmisioni":    trans_al,
        "uleset":          seats_i if seats_i else None,
        "vin":             vin_norm,
        "engine_cc":       engine_cc,
        "images":          raw.get("images", []),
        "listing_url":     detail_url or "",
        "opsionet":        opsionet,
        "raporti_url":     raporte,
    }

# ---------------- List helpers (thumb + url) ----------------
def extract_listing_thumb(row):
    def pick_src(img):
        for a in ["src","data-src","data-lazy","data-lazy-src","data-original","data-origin"]:
            try:
                v=img[a]
                if v and "carpicture" in v: return v
            except: pass
        try: return img["src"]
        except: return ""
    try:
        thumbs=row.find_by_css("img.thumb")
        if thumbs:
            src=pick_src(thumbs.first)
            if src: return upgrade_list(normalize_img_urls([src]))[0]
    except: pass
    try:
        imgs=row.find_by_tag("img")
        if imgs:
            src=pick_src(imgs.first)
            if src: return upgrade_list(normalize_img_urls([src]))[0]
    except: pass
    return ""

def absolutize(href):
    if not href: return ""
    return href if href.startswith("http") else "https://www.encar.com"+(href if href.startswith("/") else "/"+href)

def click_detail_and_get_url(browser, row, retries=3):
    sels=['a[data-enlog-dt-eventname="차량상세"]','a._link[target="_blank"]','td.inf a[href*="dc_cardetailview"]','a[href*="dc_cardetailview"]']
    href=""
    for sel in sels:
        links=row.find_by_css(sel)
        if not links: continue
        for _ in range(retries):
            el=row.find_by_css(sel).first
            try: href = el["href"]
            except: href=""
            try:
                browser.execute_script("arguments[0].scrollIntoView({block:'center'});", el._element); time.sleep(0.12)
                (el.click() if el.visible else browser.execute_script("arguments[0].click();", el._element))
                return absolutize(href)
            except (ElementNotInteractableException, ElementClickInterceptedException, StaleElementReferenceException):
                time.sleep(0.25)
            except: time.sleep(0.25)
    try:
        el=row.find_by_tag("a").first; href=el["href"]
        browser.execute_script("arguments[0].scrollIntoView({block:'center'});", el._element); time.sleep(0.12)
        (el.click() if el.visible else browser.execute_script("arguments[0].click();", el._element))
        return absolutize(href)
    except: return ""

def switch_to_new_tab(browser, prev_count, timeout=8):
    t0=time.time()
    while time.time()-t0<timeout:
        if len(browser.windows)>prev_count:
            browser.windows[-1].is_current=True; return True
        time.sleep(0.2)
    return False

def _join_blob(parts):
    return " ".join(str(p) for p in parts if p).strip().lower()

def _normalize_color(text, features_blob=""):
    blob = _join_blob([text, features_blob])
    rules = [
        ("White", r"\b(white|pearl|ivory|snow|alpine)\b|흰색|화이트|하양"),
        ("Black", r"\b(black|ebony|onyx|phantom)\b|검정|블랙"),
        ("Silver", r"\b(silver|platinum|chrome|argent|silber)\b|실버|은색"),
        ("Gray",   r"\b(gray|grey|graphite|gunmetal|anthracite)\b|회색|그레이"),
        ("Blue",   r"\b(blue|navy|azure|cobalt|sapphire)\b|파랑|블루"),
        ("Red",    r"\b(red|crimson|ruby|burgundy|wine)\b|빨강|레드|와인"),
        ("Green",  r"\b(green|emerald|olive|mint)\b|초록|그린"),
        ("Brown",  r"\b(brown|bronze|chocolate|copper)\b|브라운"),
        ("Beige",  r"\b(beige|sand|champagne|taupe)\b|베이지"),
        ("Yellow", r"\b(yellow|golden)\b|노랑|옐로"),
        ("Orange", r"\b(orange|tangerine|amber)\b|오렌지"),
        ("Purple", r"\b(purple|violet|lilac)\b|보라"),
        ("Gold",   r"\b(gold|golden)\b|골드"),
    ]
    for label, rx in rules:
        if re.search(rx, blob):
            return label
    return "Other"

# ---------------- Paging ----------------
def go_to_page(browser, page_no, timeout=10):
    def wait_rows():
        t0 = time.time()
        while time.time()-t0 < timeout:
            try:
                if browser.is_element_present_by_css('tr[data-index="0"]', wait_time=0.5):
                    return True
            except:
                pass
            time.sleep(0.2)
        return False

    try:
        js = f"""
        (function(targetPage){{
          function parseHash(){{
            try{{
              var h = location.hash || '';
              var m = h.match(/#!(.*)$/);
              if(!m) return null;
              return JSON.parse(decodeURIComponent(m[1]));
            }}catch(e){{ return null; }}
          }}
          var o = parseHash() || {{}};
          o.page = targetPage;
          if (!o.limit) o.limit = {PER_PAGE};
          var next = '#!' + encodeURIComponent(JSON.stringify(o));
          if (location.hash !== next) {{
            location.hash = next;
          }} else {{
            location.hash = '';
            location.hash = next;
          }}
        }})(%d);
        """ % int(page_no)
        browser.execute_script(js)
        if wait_rows():
            force_load_list_rows(browser, want=PER_PAGE)
            return True
    except:
        pass

    try:
        sels = [
            f'a[data-page="{page_no}"]',
            f'a[aria-label="Go to page {page_no}"]',
            f'button[aria-label="Go to page {page_no}"]',
            'a[rel="next"], button[rel="next"], a[aria-label*="Next"], button[aria-label*="Next"]'
        ]
        for sel in sels:
            if browser.is_element_present_by_css(sel, wait_time=2):
                el = browser.find_by_css(sel).first
                try:
                    browser.execute_script("arguments[0].scrollIntoView({block:'center'});", el._element)
                except: pass
                try: el.click()
                except: 
                    try: browser.execute_script("arguments[0].click();", el._element)
                    except: pass
                if wait_rows():
                    force_load_list_rows(browser, want=PER_PAGE)
                    return True
    except:
        pass
    return False

def get_paging_info(browser):
    st = get_full_state(browser) if wait_for_state(browser, 2) else {}
    page = 1
    total_pages = None
    try:
        txt = json.dumps(st).lower()
        m1 = re.search(r'"page"\s*:\s*(\d+)', txt)
        m2 = re.search(r'"pagecount"\s*:\s*(\d+)', txt) or re.search(r'"totalpages"\s*:\s*(\d+)', txt)
        if m1:
            page = int(m1.group(1))
        if m2:
            total_pages = int(m2.group(1))
    except:
        pass
    return page, total_pages



@contextmanager
def build_browser():
    """
    One unique Chrome profile per run.
    No headless=False, no shared user-data-dir.
    """
    opts = Options()

    # Headless + CI-safe flags
    opts.add_argument("--headless=new")
    opts.add_argument("--no-sandbox")
    opts.add_argument("--disable-dev-shm-usage")
    opts.add_argument("--window-size=1920,1080")
    opts.add_argument("--lang=en-US")
    opts.add_argument("--no-first-run")
    opts.add_argument("--no-default-browser-check")
    opts.add_argument("--disable-extensions")
    opts.add_argument("--disable-gpu")
    opts.add_argument("--disable-features=NetworkServiceInProcess")
    opts.add_argument("--remote-debugging-port=9222")
    # Reduce “automation” fingerprints
    opts.add_experimental_option("excludeSwitches", ["enable-automation"])
    opts.add_experimental_option("useAutomationExtension", False)
    # Respect setup-chrome path if provided
    chrome_bin = os.environ.get("CHROME_BIN") or \
                 shutil.which("google-chrome") or \
                 shutil.which("chrome") or \
                 shutil.which("chromium") or \
                 shutil.which("chromium-browser")
    if chrome_bin:
        opts.binary_location = chrome_bin
    # UA helps some sites in headless mode
    opts.add_argument(
        "--user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 "
        "(KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36"
    )

    # Auto-translate KR -> EN
    opts.add_experimental_option("prefs", {
        "intl.accept_languages": "en-US,en",
        "translate_whitelists": {"ko": "en"},
        "translate": {"enabled": True},
    })

    # ALWAYS use a unique, throwaway user-data-dir
    profile_dir = tempfile.mkdtemp(prefix=f"encar-chrome-{uuid.uuid4().hex[:8]}-")
    cache_dir   = tempfile.mkdtemp(prefix=f"encar-cache-{uuid.uuid4().hex[:8]}-")
    opts.add_argument(f"--user-data-dir={profile_dir}")
    opts.add_argument(f"--profile-directory=Profile-Default")
    opts.add_argument(f"--disk-cache-dir={cache_dir}")

    # DO NOT pass headless=False here – we already set headless via opts
    def _chrome_major(path):
        try:
            out = subprocess.check_output([path, "--version"], text=True).strip()
            # e.g. "Chromium 143.0.7445.0" → "143"
            import re
            m = re.search(r"(\d+)\.", out)
            return m.group(1) if m else None
        except Exception:
            return None
    major = _chrome_major(chrome_bin) if chrome_bin else None
    from webdriver_manager.chrome import ChromeDriverManager
    driver_path = ChromeDriverManager(version=major if major else None).install()
    service = Service(driver_path)
    br = Browser("chrome", options=opts, service=service)
    try:
        # Reasonable timeouts for CI
        try:
            br.driver.set_page_load_timeout(45)
            br.driver.set_script_timeout(30)
        except Exception:
            pass
        yield br
    finally:
         try:
             br.quit()
         except Exception:
             pass
        
def main():
    with build_browser() as browser:
        browser.visit(BASE_URL)
        time.sleep(3)
        wait_ready(browser, 10)
        ensure_english(browser, 5)

        # Force full list on first page
        cnt = force_load_list_rows(browser, want=PER_PAGE)
        print(f"[list] rows loaded: {cnt}")

        os.makedirs(CSV_DIR, exist_ok=True)
        csv_path = os.path.join(CSV_DIR, CSV_NAME)

        fields = [
            "prodhuesi","modeli","varianti","viti",
            "cmimi_eur","kilometrazhi_km","karburanti","ngjyra",
            "transmisioni","uleset","vin","engine_cc","images","listing_url",
            "opsionet","raporti_url"
        ]

        with open(csv_path, "w", newline="", encoding="utf-8-sig") as f:
            writer = csv.DictWriter(f, fieldnames=fields)
            writer.writeheader()

            total_done = 0
            current_page = 1
            _, total_pages = get_paging_info(browser)

            while total_done < MAX_LISTINGS:
                if current_page > 1:
                    if not go_to_page(browser, current_page):
                        if total_pages and current_page > total_pages:
                            break
                        if not go_to_page(browser, current_page):
                            break
                    # ensure list loaded on new page
                    cnt = force_load_list_rows(browser, want=PER_PAGE)
                    print(f"[list] page {current_page} rows loaded: {cnt}")

                time.sleep(0.8)
                ensure_english(browser, 3)

                list_state = get_full_state(browser) if wait_for_state(browser, 3) else {}
                state_records = get_list_records_from_state(list_state)

                # always use the actual number of visible rows
                rows = browser.find_by_css("tr[data-index]")
                rows_count = len(rows)
                if rows_count < PER_PAGE:
                    # try once more to coax lazy load
                    extra = force_load_list_rows(browser, want=PER_PAGE)
                    rows = browser.find_by_css("tr[data-index]")
                    rows_count = len(rows)
                    print(f"[list] visible rows now: {rows_count} (force_load returned {extra})")

                row_index = 0
                while row_index < rows_count and total_done < MAX_LISTINGS:
                    # refresh rows reference in case DOM changed
                    rows = browser.find_by_css("tr[data-index]")
                    if not rows or row_index >= len(rows):
                        break

                    row = rows[row_index]

                    rec = state_records[row_index] if row_index < len(state_records) else None
                    if rec and not rec.get("title"):
                        rec = None
                    if not rec:
                        rec = list_row_dom_extract(row)
                    else:
                        try:
                            rec["row_html"] = row.html
                        except:
                            rec["row_html"] = ""

                    title     = (rec.get("title") or "").strip()
                    priceText = (rec.get("priceText") or "").strip()
                    priceNum  = rec.get("priceNum", None)
                    row_html  = rec.get("row_html") or ""

                    brand, model, variant = parse_title_brand_model_variant(title)
                    krw_list, eur_list = parse_list_price_eur(priceText, priceNum, row_html)

                    panel = _get_inline_panel_html(browser, row_index)
                    inline_vals = parse_inline_detail_values(panel)
                    color_hint_raw = inline_vals.get("color_raw")
                    seats_hint     = inline_vals.get("seats") or 0

                    inline_report_url = get_inline_report_url(browser, row_index)
                    listing_thumb = extract_listing_thumb(row)

                    prev_tabs = len(browser.windows)
                    detail_url = click_detail_and_get_url(browser, row)

                    if detail_url and switch_to_new_tab(browser, prev_tabs, 8):
                        time.sleep(0.9)
                        ensure_english(browser, 4)
                        raw = scrape_detail_raw(browser)
                        try:
                            browser.windows.current.close()
                        except:
                            pass
                        try:
                            browser.windows[0].is_current = True
                        except:
                            pass
                        time.sleep(0.2)
                    else:
                        raw = {
                            "manufacturer": "", "model": "", "grade": "",
                            "form_year": "", "year_month": "",
                            "ad_price": "", "price_text": priceText,
                            "mileage": "", "fuel": "", "color": "",
                            "transmission": "", "seats": "",
                            "vin": "", "engine_cc": "",
                            "images": [], "body_type": "",
                            "features": [], "report_links": [], "carid": ""
                        }

                    imgs = raw.get("images", [])
                    if listing_thumb:
                        imgs = [listing_thumb] + [u for u in imgs if u != listing_thumb]
                        raw["images"] = imgs[:20]

                    list_hint = {
                        "prodhuesi": brand,
                        "modeli": model,
                        "varianti": variant,
                        "cmimi_eur": eur_list,
                        "engine_cc_hint": 0,
                        "color_hint": color_hint_raw,
                        "seats_hint": seats_hint,
                        "inline_report_url": inline_report_url,
                        "title": title,
                    }

                    alb = to_albanian_schema(raw, detail_url, list_hint)

                    row_out = {
                        "prodhuesi": alb["prodhuesi"],
                        "modeli": alb["modeli"],
                        "varianti": alb["varianti"],
                        "viti": alb["viti"],
                        "cmimi_eur": alb["cmimi_eur"],
                        "kilometrazhi_km": alb["kilometrazhi_km"],
                        "karburanti": alb["karburanti"],
                        "ngjyra": alb["ngjyra"],
                        "transmisioni": alb["transmisioni"],
                        "uleset": "" if alb["uleset"] is None else str(alb["uleset"]),
                        "vin": alb["vin"],
                        "engine_cc": alb["engine_cc"],
                        "images": ";".join(alb.get("images", [])),
                        "listing_url": alb["listing_url"],
                        "opsionet": alb["opsionet"],
                        "raporti_url": alb["raporti_url"],
                    }
                    row_out = fill_blanks_in_row(row_out)

                    # ✅ Correct WRITE_DB logic
                    if WRITE_DB:
                        missing = [v for v in ("DB_HOST","DB_PORT","DB_USERNAME","DB_PASSWORD","DB_DATABASE") if not os.getenv(v)]
                        if missing:
                            print(f"[skip-db] missing {', '.join(missing)}; skipping DB upsert")
                        else:
                            upsert_vehicle(row_out)

                    # write the CSV row regardless of DB
                    writer.writerow(row_out)

                    total_done += 1
                    row_index += 1
                    print(f"✅ {total_done}/{MAX_LISTINGS} (page {current_page}, row {row_index})")

                current_page += 1
                _, tp = get_paging_info(browser)
                total_pages = tp or total_pages

        print(f"🎯 Finished. Saved to {csv_path}")


if __name__ == "__main__":
    main()
