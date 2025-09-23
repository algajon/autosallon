<?php

namespace App\Console\Commands;

use App\Models\Vehicle;
use Illuminate\Console\Command;

class ImportVehicles extends Command
{
    protected $signature = 'vehicles:import {path : Path to CSV} {--truncate : Truncate vehicles table before import}';
    protected $description = 'Import vehicles from a standardized CSV (Albanian columns)';

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return self::FAILURE;
        }

        if ($this->option('truncate')) {
            Vehicle::truncate();
            $this->info('vehicles table truncated.');
        }

        if (!$h = fopen($path, 'r')) {
            $this->error("Cannot open {$path}");
            return self::FAILURE;
        }

        $headers = fgetcsv($h);
        if (!$headers) {
            $this->error('CSV has no header row.');
            return self::FAILURE;
        }

        // --- header normalizer (lowercase + strip BOM + accents + punctuation) ---
        $norm = function (?string $s): string {
            $s = (string)$s;
            // strip BOM
            $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);
            $s = trim(mb_strtolower($s, 'UTF-8'));
            // replace a few Albanian accented letters so we can match loosely
            $s = strtr($s, [
                'ç' => 'c', 'ë' => 'e',
                'Ç' => 'c', 'Ë' => 'e',
            ]);
            // remove brackets and extra punctuation that sometimes appears in labels
            $s = preg_replace('/[\(\)\[\]\{\}]/', ' ', $s);
            $s = preg_replace('/\s+/', ' ', $s);
            return trim($s);
        };

        // Build a normalized map of many possible header variants -> our canonical keys
        $map = [
            // pretty labels (normalized)
            'prodhuesi'                => 'prodhuesi',
            'modeli'                   => 'modeli',
            'varianti'                 => 'varianti',
            'viti'                     => 'viti',
            'cmimi eur'                => 'cmimi_eur',
            'cmimi (eur)'              => 'cmimi_eur',
            'kilometrazhi km'          => 'kilometrazhi_km',
            'karburanti'               => 'karburanti',
            'ngjyra'                   => 'ngjyra',
            'transmisioni'             => 'transmisioni',
            'uleset'                   => 'uleset',
            'vin'                      => 'vin',
            'engine cc'                => 'engine_cc',
            'imazhe url'               => 'images',
            'imazhe'                   => 'images',
            'listing url'              => 'listing_url',
            'opsionet'                 => 'opsionet',
            'raporti url'              => 'raporti_url',

            // raw lowercase DB column headers (exact)
            'prodhuesi'                => 'prodhuesi',
            'modeli'                   => 'modeli',
            'varianti'                 => 'varianti',
            'viti'                     => 'viti',
            'cmimi_eur'                => 'cmimi_eur',
            'kilometrazhi_km'          => 'kilometrazhi_km',
            'karburanti'               => 'karburanti',
            'ngjyra'                   => 'ngjyra',
            'transmisioni'             => 'transmisioni',
            'uleset'                   => 'uleset',
            'vin'                      => 'vin',
            'engine_cc'                => 'engine_cc',
            'images'                   => 'images',
            'listing_url'              => 'listing_url',
            'opsionet'                 => 'opsionet',
            'raporti_url'              => 'raporti_url',

            // possible English fallbacks
            'manufacturer'             => 'prodhuesi',
            'model'                    => 'modeli',
            'grade'                    => 'varianti',
            'year'                     => 'viti',
            'price'                    => 'cmimi_eur',
            'mileage'                  => 'kilometrazhi_km',
            'fuel'                     => 'karburanti',
            'color'                    => 'ngjyra',
            'transmission'             => 'transmisioni',
            'seats'                    => 'uleset',
            'enginecc'                 => 'engine_cc',
            'report links'             => 'raporti_url',
            'features'                 => 'opsionet',
        ];

        // Index headers -> our keys using normalization
        $idx = [];
        foreach ($headers as $i => $hname) {
            $key = $map[$norm($hname)] ?? null;
            if ($key) $idx[$key] = $i;
        }

        // quick guard: if we only matched vin/images earlier, warn loudly
        if (count($idx) <= 2) {
            $this->warn('Heads up: only a couple of headers matched. Check your CSV header names.');
        }

        $rows = 0;
        while (($row = fgetcsv($h)) !== false) {
            $rows++;

            $get = function (string $key, $default = null) use ($idx, $row) {
                if (!array_key_exists($key, $idx)) return $default;
                $v = $row[$idx[$key]] ?? $default;
                return is_string($v) ? trim($v) : $v;
            };

            // images: JSON array or ; , newline separated
            $images = [];
            $imagesRaw = (string) $get('images', '');
            if ($imagesRaw !== '') {
                $trim = trim($imagesRaw);
                if (strlen($trim) > 1 && $trim[0] === '[' && substr($trim, -1) === ']') {
                    $decoded = json_decode($trim, true);
                    if (is_array($decoded)) {
                        $images = array_values(array_filter(array_map('trim', $decoded)));
                    }
                }
                if (empty($images)) {
                    $images = array_values(array_filter(
                        array_map('trim', preg_split('/[;,\r\n]+/', $imagesRaw) ?: []),
                        fn ($u) => $u !== ''
                    ));
                }
            }

            Vehicle::create([
                'prodhuesi'        => (string) $get('prodhuesi', ''),
                'modeli'           => (string) $get('modeli', ''),
                'varianti'         => (string) $get('varianti', ''),
                'viti'             => ($v = (int) $get('viti', 0)) ? $v : null,

                'cmimi_eur'        => ($p = (int) $get('cmimi_eur', 0)) ? $p : null,
                'kilometrazhi_km'  => ($k = (int) $get('kilometrazhi_km', 0)) ? $k : null,
                'karburanti'       => (string) $get('karburanti', ''),
                'ngjyra'           => (string) $get('ngjyra', ''),
                'transmisioni'     => (string) $get('transmisioni', ''),
                'uleset'           => ($s = (int) $get('uleset', 0)) ? $s : null,
                'vin'              => (string) $get('vin', ''),

                'engine_cc'        => ($cc = (int) $get('engine_cc', 0)) ? $cc : null,
                'images'           => $images,
                'listing_url'      => (string) $get('listing_url', ''),
                'opsionet'         => (string) $get('opsionet', ''),
                'raporti_url'      => (string) $get('raporti_url', ''),
            ]);
        }

        fclose($h);
        $this->info("Imported {$rows} row(s) from {$path}");
        return self::SUCCESS;
    }
}
