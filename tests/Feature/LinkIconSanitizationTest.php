<?php

namespace Tests\Feature;

use App\Http\Livewire\BuatTautan;
use App\Models\Tautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinkIconSanitizationTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '"><script>alert(1)</script><i class="';

    public function test_icon_class_allowlist(): void
    {
        $this->assertSame('fa-solid fa-house', Tautan::sanitizeIconClass('fa-solid fa-house'));
        $this->assertSame('fab fa-instagram', Tautan::sanitizeIconClass(' fab fa-instagram '));
        $this->assertSame('', Tautan::sanitizeIconClass(self::PAYLOAD));
        $this->assertSame('', Tautan::sanitizeIconClass('fa-solid fa-house" onmouseover="alert(1)'));
        $this->assertSame('', Tautan::sanitizeIconClass("fa-solid\nfa-house"));
        $this->assertSame('', Tautan::sanitizeIconClass(['fa-solid']));
    }

    public function test_set_icon_rejects_markup_from_the_client(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(BuatTautan::class)
            ->call('selectIcon', 0)
            ->call('setIcon', self::PAYLOAD)
            ->assertSet('links.0.icon', '')
            ->call('selectIcon', 0)
            ->call('setIcon', 'fa-solid fa-house')
            ->assertSet('links.0.icon', 'fa-solid fa-house');
    }

    public function test_icon_set_directly_on_the_property_is_not_stored(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(BuatTautan::class)
            ->set('judul', 'Tautan Icon')
            ->set('slug', 'tautan-icon')
            ->set('links.0.icon', self::PAYLOAD)
            ->call('saveToDatabase')
            ->assertHasNoErrors();

        $tautan = Tautan::where('user_id', $user->id)->sole();
        $this->assertSame('', $tautan->links[0]['icon']);
    }

    public function test_public_page_escapes_icons_already_in_the_database(): void
    {
        $user = User::factory()->create();

        $tautan = Tautan::create([
            'user_id' => $user->id,
            'title' => 'Data Lama',
            'slug' => 'data-lama',
            'links' => [
                ['judul' => 'Jahat', 'url' => 'https://example.com', 'icon' => self::PAYLOAD, 'active' => true],
                ['judul' => 'Rumah', 'url' => 'https://example.org', 'icon' => 'fa-solid fa-house', 'active' => true],
            ],
            'is_active' => true,
        ]);

        $this->get('/'.$tautan->slug)
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<i class="fa-solid fa-house link-icon"></i>', false)
            ->assertSee('Jahat');
    }
}
