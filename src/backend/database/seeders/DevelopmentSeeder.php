<?php

namespace Database\Seeders;

use App\Models\Merchant;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Development seeder — local testing only.
 * Creates 2 merchants, 2 outlets each, 1 admin user each.
 * Run: php artisan db:seed --class=DevelopmentSeeder
 */
class DevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        // ── Merchant A: Brew & Co (Coffee Chain) ──────────────
        $brewCo = Merchant::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'Brew & Co',
            'category' => 'cafe',
            'city'     => 'Mumbai',
            'state'    => 'Maharashtra',
            'pincode'  => '400050',
            'phone'    => '9900001111',
            'email'    => 'admin@brewco.com',
        ]);

        $brewBandra = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $brewCo->id,
            'name'        => 'Brew & Co — Bandra',
            'address'     => '12, Linking Road, Bandra West',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400050',
            'latitude'    => 19.0596,
            'longitude'   => 72.8295,
        ]);

        $brewAndheri = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $brewCo->id,
            'name'        => 'Brew & Co — Andheri',
            'address'     => '45, Andheri West, SV Road',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400053',
            'latitude'    => 19.1197,
            'longitude'   => 72.8464,
        ]);

        User::create([
            'name'        => 'Alice (Brew & Co Admin)',
            'email'       => 'alice@brewco.com',
            'password'    => Hash::make('password'),
            'merchant_id' => $brewCo->id,
            'outlet_id'   => null,
            'role'        => 1, // admin
        ]);

        // ── Merchant B: FitZone (Gym Chain) ───────────────────
        $fitZone = Merchant::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'FitZone Gyms',
            'category' => 'gym',
            'city'     => 'Mumbai',
            'state'    => 'Maharashtra',
            'pincode'  => '400050',
            'phone'    => '9900002222',
            'email'    => 'admin@fitzone.com',
        ]);

        $fitBandra = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $fitZone->id,
            'name'        => 'FitZone — Bandra',
            'address'     => '3rd Floor, Turner Road, Bandra West',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400050',
            'latitude'    => 19.0612,
            'longitude'   => 72.8304,
        ]);

        $fitAndheri = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $fitZone->id,
            'name'        => 'FitZone — Andheri',
            'address'     => '2nd Floor, Infinity Mall, Andheri',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400053',
            'latitude'    => 19.1183,
            'longitude'   => 72.8472,
        ]);

        User::create([
            'name'        => 'Bob (FitZone Admin)',
            'email'       => 'bob@fitzone.com',
            'password'    => Hash::make('password'),
            'merchant_id' => $fitZone->id,
            'outlet_id'   => null,
            'role'        => 1, // admin
        ]);

        // ── Merchant C: Bella Salon ───────────────────────────
        $bella = Merchant::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'Bella Salon',
            'category' => 'salon',
            'city'     => 'Mumbai',
            'state'    => 'Maharashtra',
            'pincode'  => '400050',
            'phone'    => '9900003333',
            'email'    => 'admin@bellasalon.com',
        ]);

        $bellaBandra = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $bella->id,
            'name'        => 'Bella Salon — Bandra',
            'address'     => '7, Hill Road, Bandra West',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400050',
            'latitude'    => 19.0601,
            'longitude'   => 72.8312,
        ]);

        User::create([
            'name'        => 'Carol (Bella Admin)',
            'email'       => 'carol@bellasalon.com',
            'password'    => Hash::make('password'),
            'merchant_id' => $bella->id,
            'outlet_id'   => null,
            'role'        => 1,
        ]);

        // ── Merchant D: BookNook ──────────────────────────────
        $bookNook = Merchant::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'BookNook',
            'category' => 'bookstore',
            'city'     => 'Mumbai',
            'state'    => 'Maharashtra',
            'pincode'  => '400050',
            'phone'    => '9900004444',
            'email'    => 'admin@booknook.com',
        ]);

        $bookNookBandra = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $bookNook->id,
            'name'        => 'BookNook — Bandra',
            'address'     => '22, Chapel Road, Bandra West',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400050',
            'latitude'    => 19.0589,
            'longitude'   => 72.8300,
        ]);

        User::create([
            'name'        => 'Dave (BookNook Admin)',
            'email'       => 'dave@booknook.com',
            'password'    => Hash::make('password'),
            'merchant_id' => $bookNook->id,
            'outlet_id'   => null,
            'role'        => 1,
        ]);

        // ── Merchant E: GreenBowl (Restaurant) ───────────────
        $greenBowl = Merchant::create([
            'uuid'     => (string) Str::uuid(),
            'name'     => 'GreenBowl',
            'category' => 'restaurant',
            'city'     => 'Mumbai',
            'state'    => 'Maharashtra',
            'pincode'  => '400053',
            'phone'    => '9900005555',
            'email'    => 'admin@greenbowl.com',
        ]);

        $greenBowlAndheri = Outlet::create([
            'uuid'        => (string) Str::uuid(),
            'merchant_id' => $greenBowl->id,
            'name'        => 'GreenBowl — Andheri',
            'address'     => 'G-12, Andheri West, SV Road',
            'city'        => 'Mumbai',
            'state'       => 'Maharashtra',
            'pincode'     => '400053',
            'latitude'    => 19.1190,
            'longitude'   => 72.8468,
        ]);

        User::create([
            'name'        => 'Eve (GreenBowl Admin)',
            'email'       => 'eve@greenbowl.com',
            'password'    => Hash::make('password'),
            'merchant_id' => $greenBowl->id,
            'outlet_id'   => null,
            'role'        => 1,
        ]);

        $this->command->info('');
        $this->command->info('✓ Development seed complete');
        $this->command->info('');
        $this->command->table(
            ['Merchant', 'ID', 'Login Email', 'Password'],
            [
                [$brewCo->name,   $brewCo->id,   'alice@brewco.com',        'password'],
                [$fitZone->name,  $fitZone->id,  'bob@fitzone.com',          'password'],
                [$bella->name,    $bella->id,    'carol@bellasalon.com',     'password'],
                [$bookNook->name, $bookNook->id, 'dave@booknook.com',        'password'],
                [$greenBowl->name,$greenBowl->id,'eve@greenbowl.com',        'password'],
            ]
        );
        $this->command->info('');
        $this->command->table(
            ['Outlet', 'ID', 'Merchant', 'Pincode'],
            [
                [$brewBandra->name,     $brewBandra->id,     'Brew & Co',   '400050'],
                [$brewAndheri->name,    $brewAndheri->id,    'Brew & Co',   '400053'],
                [$fitBandra->name,      $fitBandra->id,      'FitZone Gyms','400050'],
                [$fitAndheri->name,     $fitAndheri->id,     'FitZone Gyms','400053'],
                [$bellaBandra->name,    $bellaBandra->id,    'Bella Salon', '400050'],
                [$bookNookBandra->name, $bookNookBandra->id, 'BookNook',    '400050'],
                [$greenBowlAndheri->name,$greenBowlAndheri->id,'GreenBowl', '400053'],
            ]
        );
    }
}
