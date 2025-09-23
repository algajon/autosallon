<?php

namespace App\Http\Controllers;

use Illuminate\Support\Arr;

class VehicleCsvController extends Controller
{
    public function index()
    {
        $path = storage_path('app/imports/cars.csv');
        if (!file_exists($path)) {
            $alt = storage_path('app/imports/cars.csv');
            if (file_exists($alt)) $path = $alt;
        }

        $vehicles = [];
        if (file_exists($path)) {
            if (($h = fopen($path, 'r')) !== false) {
                $headers = fgetcsv($h);
                while (($row = fgetcsv($h)) !== false) {
                    $vehicles[] = array_combine($headers, $row);
                }
                fclose($h);
            }
        }

        return view('index', compact('vehicles'));
    }
}
