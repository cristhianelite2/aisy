<?php

namespace Tests\Feature;

use App\Models\Business;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_slug_and_default_branch_features(): void
    {
        $b1 = Business::create([
            'name' => 'Estética Lupita!',
            'slug' => Business::generateUniqueSlug('Estética Lupita!'),
            'branch' => 'estetica',
            'owner_name' => 'Lupita Pérez',
            'owner_phone' => '5215512345678',
            'features' => Business::defaultFeaturesForBranch('estetica'),
        ]);

        $b2 = Business::create([
            'name' => 'Estética Lupita!',
            'slug' => Business::generateUniqueSlug('Estética Lupita!'),
            'branch' => 'estetica',
            'owner_name' => 'Lupita Dos',
            'owner_phone' => '5215587654321',
            'features' => Business::defaultFeaturesForBranch('estetica'),
        ]);

        $this->assertSame('estetica-lupita', $b1->slug);
        $this->assertSame('estetica-lupita-2', $b2->slug);
        $this->assertTrue($b1->isFeatureEnabled('agenda'));
        $b1->toggleFeature('agenda', false);
        $this->assertFalse($b1->fresh()->isFeatureEnabled('agenda'));
    }
}
