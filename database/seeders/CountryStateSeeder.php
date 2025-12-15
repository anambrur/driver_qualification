<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;
use App\Models\State;
use Illuminate\Support\Facades\Http;

class CountryStateSeeder extends Seeder
{
    public function run()
    {
        // Method 1: Using REST Countries API (No API key required)
        $this->seedFromRestCountriesAPI();

        // Method 2: Using static data (commented out - use if API fails)
        // $this->seedStaticData();
    }

    /**
     * Method 1: Fetch data from REST Countries API
     */
    private function seedFromRestCountriesAPI()
    {
        try {
            $response = Http::get('https://restcountries.com/v3.1/all?fields=name,cca2,idd,currencies,cioc');

            if ($response->successful()) {
                $countries = $response->json();

                foreach ($countries as $countryData) {
                    // Create country
                    $country = Country::create([
                        'name' => $countryData['name']['common'],
                        'iso_code' => $countryData['cca2'],
                        'phone_code' => $this->extractPhoneCode($countryData['idd'] ?? []),
                        'currency_code' => $this->extractCurrencyCode($countryData['currencies'] ?? []),
                        'currency_name' => $this->extractCurrencyName($countryData['currencies'] ?? []),
                    ]);

                    // $this->command->info("Added country: {$country->name}");

                    // For US and a few other countries, add states
                    if (in_array($country->iso_code, ['US', 'CA', 'IN', 'BR', 'CN'])) {
                        $this->seedStatesForCountry($country->id, $country->iso_code);
                    }
                }

                // $this->command->info('Successfully seeded countries and states from API!');
            } else {
                // $this->command->error('Failed to fetch data from API. Using static data instead.');
                $this->seedStaticData();
            }
        } catch (\Exception $e) {
            $this->command->error('API Error: ' . $e->getMessage());
            $this->command->info('Using static data instead...');
            $this->seedStaticData();
        }
    }

    /**
     * Method 2: Use static data as fallback
     */
    private function seedStaticData()
    {
        // Clear existing data
        State::query()->delete();
        Country::query()->delete();

        // Sample countries data
        $countries = [
            [
                'name' => 'United States',
                'iso_code' => 'US',
                'phone_code' => '+1',
                'currency_code' => 'USD',
                'currency_name' => 'US Dollar',
            ],
            [
                'name' => 'Canada',
                'iso_code' => 'CA',
                'phone_code' => '+1',
                'currency_code' => 'CAD',
                'currency_name' => 'Canadian Dollar',
            ],
            [
                'name' => 'United Kingdom',
                'iso_code' => 'GB',
                'phone_code' => '+44',
                'currency_code' => 'GBP',
                'currency_name' => 'British Pound',
            ],
            [
                'name' => 'India',
                'iso_code' => 'IN',
                'phone_code' => '+91',
                'currency_code' => 'INR',
                'currency_name' => 'Indian Rupee',
            ],
            [
                'name' => 'Australia',
                'iso_code' => 'AU',
                'phone_code' => '+61',
                'currency_code' => 'AUD',
                'currency_name' => 'Australian Dollar',
            ],
        ];

        foreach ($countries as $countryData) {
            $country = Country::create($countryData);
            $this->command->info("Added country: {$country->name}");

            // Add states for specific countries
            if (in_array($country->iso_code, ['US', 'CA', 'IN'])) {
                $this->seedStatesForCountry($country->id, $country->iso_code);
            }
        }
    }

    /**
     * Seed states for a specific country
     */
    private function seedStatesForCountry($countryId, $countryCode)
    {
        try {
            $response = Http::post('https://countriesnow.space/api/v0.1/countries/states', [
                'iso2' => $countryCode
            ]);

            if ($response->successful()) {
                $states = $response->json('data.states');

                foreach ($states as $state) {
                    State::create([
                        'country_id' => $countryId,
                        'name'       => $state['name'],
                        'code'       => $state['state_code'] ?? null,
                    ]);
                }

                $this->command->info("Added " . count($states) . " states for {$countryCode}");
            } else {
                $this->command->error("Failed to fetch states for {$countryCode}");
            }
        } catch (\Exception $e) {
            $this->command->error("State API error for {$countryCode}: " . $e->getMessage());
        }
    }


    /**
     * Extract phone code from API response
     */
    private function extractPhoneCode($idd)
    {
        if (empty($idd['root']) || empty($idd['suffixes'])) {
            return null;
        }

        $suffix = is_array($idd['suffixes']) && !empty($idd['suffixes']) ? $idd['suffixes'][0] : '';
        return $idd['root'] . $suffix;
    }

    /**
     * Extract currency code from API response
     */
    private function extractCurrencyCode($currencies)
    {
        if (empty($currencies)) {
            return null;
        }

        $currency = reset($currencies);
        return $currency['code'] ?? null;
    }

    /**
     * Extract currency name from API response
     */
    private function extractCurrencyName($currencies)
    {
        if (empty($currencies)) {
            return null;
        }

        $currency = reset($currencies);
        return $currency['name'] ?? null;
    }
}
