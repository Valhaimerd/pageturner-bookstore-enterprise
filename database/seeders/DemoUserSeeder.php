<?php

namespace Database\Seeders;

use App\Models\TwoFactorSecret;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Valhaimerd',
                'email' => 'valhaimerd@gmail.com',
                'role' => 'admin',
                'subscription_tier' => 'premium',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09934593138',
                'default_address_line_1' => 'PageTurner Main Office',
                'default_address_line_2' => 'Corrales Avenue',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Manager Admin',
                'email' => 'manager@pageturner.test',
                'role' => 'admin',
                'subscription_tier' => 'premium',
                'verified' => true,
                'two_factor_enabled' => true,
                'phone' => '09170000002',
                'default_address_line_1' => 'Admin Building',
                'default_address_line_2' => 'Lapasan',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Palvry Clount',
                'email' => 'palvryclount@gmail.com',
                'role' => 'customer',
                'subscription_tier' => 'standard',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09170000011',
                'default_address_line_1' => 'Zone 1, Patag',
                'default_address_line_2' => 'Near Main Road',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Ria Gomez',
                'email' => 'ria@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'premium',
                'verified' => true,
                'two_factor_enabled' => true,
                'phone' => '09170000012',
                'default_address_line_1' => 'Block 4, Xavier Heights',
                'default_address_line_2' => 'House 12',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Sean Dela Cruz',
                'email' => 'sean@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'standard',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09170000013',
                'default_address_line_1' => 'Phase 2, Camaman-an',
                'default_address_line_2' => 'Apartment B',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Alya Torres',
                'email' => 'alya@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'standard',
                'verified' => false,
                'two_factor_enabled' => false,
                'phone' => '09170000014',
                'default_address_line_1' => 'Macasandig Street',
                'default_address_line_2' => null,
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Nico Fernandez',
                'email' => 'nico@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'premium',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09170000015',
                'default_address_line_1' => 'Nazareth',
                'default_address_line_2' => 'Street 5',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Mia Valdez',
                'email' => 'mia@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'standard',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09170000016',
                'default_address_line_1' => 'Carmen',
                'default_address_line_2' => 'Purok 8',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Daniel Lim',
                'email' => 'daniel@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'standard',
                'verified' => false,
                'two_factor_enabled' => false,
                'phone' => '09170000017',
                'default_address_line_1' => 'Bulua Highway',
                'default_address_line_2' => 'Unit 3',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
            [
                'name' => 'Kara Santos',
                'email' => 'kara@pageturner.test',
                'role' => 'customer',
                'subscription_tier' => 'premium',
                'verified' => true,
                'two_factor_enabled' => false,
                'phone' => '09170000018',
                'default_address_line_1' => 'Kauswagan',
                'default_address_line_2' => 'Lot 9',
                'default_city' => 'Cagayan de Oro',
                'default_province' => 'Misamis Oriental',
                'default_postal_code' => '9000',
                'default_country' => 'Philippines',
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['name'],
                    'password' => Hash::make('password123'),
                    'role' => $data['role'],
                    'subscription_tier' => $data['subscription_tier'],
                    'phone' => $data['phone'],
                    'default_address_line_1' => $data['default_address_line_1'],
                    'default_address_line_2' => $data['default_address_line_2'],
                    'default_city' => $data['default_city'],
                    'default_province' => $data['default_province'],
                    'default_postal_code' => $data['default_postal_code'],
                    'default_country' => $data['default_country'],
                    'is_active' => true,
                    'two_factor_enabled' => $data['two_factor_enabled'],
                    'email_verified_at' => $data['verified'] ? now() : null,
                ]
            );

            if ($data['two_factor_enabled']) {
                TwoFactorSecret::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'method' => 'email_otp',
                        'secret' => null,
                        'recovery_codes' => null,
                        'confirmed_at' => now(),
                        'last_used_at' => null,
                    ]
                );
            } else {
                TwoFactorSecret::where('user_id', $user->id)->delete();
            }
        }
    }
}
