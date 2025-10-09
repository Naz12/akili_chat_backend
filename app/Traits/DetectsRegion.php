<?php

namespace App\Traits;

use Illuminate\Http\Request;

trait DetectsRegion
{
    /**
     * Get the request region (e.g., 'local' or 'intl')
     */
    public function getRegion(Request $request): string
    {
        $region = $request->route('region') ?? $request->segment(3);
    
        return in_array($region, ['local', 'intl']) ? $region : 'unknown';
    }
    
    
}