<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Read-only geography lookups for the vendor onboarding form (shared Geox_* tables).
 */
class GeoController extends Controller
{
    public function countries()
    {
        return response()->json(
            DB::table('Geox_Country_Master_T')
                ->select('id', 'Country_Name')
                ->orderBy('Country_Name')
                ->get()
        );
    }

    public function regions($country)
    {
        return response()->json(
            DB::table('Geox_Region_Master_T')
                ->select('id', 'Country_Id', 'Region_Name')
                ->where('Country_Id', (int) $country)
                ->orderBy('Region_Name')
                ->get()
        );
    }

    public function districts($region)
    {
        return response()->json(
            DB::table('Geox_District_Master_T')
                ->select('id', 'Region_Id', 'District_Name')
                ->where('Region_Id', (int) $region)
                ->orderBy('District_Name')
                ->get()
        );
    }

    public function cities($district)
    {
        return response()->json(
            DB::table('Geox_City_Master_T')
                ->select('id', 'District_Id', 'City_Name')
                ->where('District_Id', (int) $district)
                ->orderBy('City_Name')
                ->get()
        );
    }
}
