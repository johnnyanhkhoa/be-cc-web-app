<?php

namespace App\Services;

use Stevebauman\Location\Facades\Location;
use hisorange\BrowserDetect\Parser as Browser;
use Illuminate\Http\Request;

class GetDeviceInfoService
{
    /**
     * Get user IP address from request
     */
    private function getUserIpAddr(): string
    {
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED'])) {
            return $_SERVER['HTTP_X_FORWARDED'];
        } elseif (isset($_SERVER['HTTP_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_FORWARDED_FOR'];
        } elseif (isset($_SERVER['HTTP_FORWARDED'])) {
            return $_SERVER['HTTP_FORWARDED'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return 'UNKNOWN';
    }

    /**
     * Get comprehensive device data
     */
    public function getDeviceData(Request $request, int $teamId, string $email, string $username): array
    {
        $ip = $request->ip() ?: $this->getUserIpAddr();
        $locData = Location::get($ip);

        return [
            'teamId' => $teamId,
            'email' => $email,
            'username' => $username,
            'http_user_agent' => $request->userAgent(),
            'device_type' => Browser::deviceType(),
            'device_name' => Browser::deviceFamily() . ' ' . Browser::deviceModel(),
            'browser_name' => Browser::browserName(),
            'platform_name' => Browser::platformName(),
            'ip_address' => $ip,
            'country_name' => $locData->countryName ?? null,
            'country_code' => $locData->countryCode ?? null,
            'region_name' => $locData->regionName ?? null,
            'region_code' => $locData->regionCode ?? null,
            'city_name' => $locData->cityName ?? null,
            'zip_code' => $locData->zipCode ?? null,
            'latitude' => $locData->latitude ?? null,
            'longitude' => $locData->longitude ?? null,
        ];
    }
}
