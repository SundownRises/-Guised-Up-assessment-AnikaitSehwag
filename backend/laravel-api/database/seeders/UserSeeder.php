<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed the application's database with test users.
     */
    public function run(): void
    {
        // Generate a deterministic 384-dim interest vector for Anika
        // Formula: sin(i * 0.1) * 0.5 for each dimension, then normalize to unit length
        $rawVector = [];
        for ($i = 0; $i < 384; $i++) {
            $rawVector[] = sin($i * 0.1) * 0.5;
        }

        // Normalize to unit length
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $rawVector)));
        $interestVector = array_map(fn($v) => $v / $magnitude, $rawVector);

        $anika = User::create([
            'name' => 'Anika',
            'email' => 'anika@guisedup.com',
            'password' => Hash::make('password'),
            'avatar_url' => null,
            'interest_vector' => $interestVector,
        ]);

        $ravi = User::create([
            'name' => 'Ravi',
            'email' => 'ravi@guisedup.com',
            'password' => Hash::make('password'),
            'avatar_url' => null,
            'interest_vector' => null,
        ]);

        // Create Sanctum tokens and output to console
        $anikaToken = $anika->createToken('seed-token')->plainTextToken;
        $raviToken = $ravi->createToken('seed-token')->plainTextToken;

        $this->command->info("Anika's token: {$anikaToken}");
        $this->command->info("Ravi's token: {$raviToken}");
    }
}
