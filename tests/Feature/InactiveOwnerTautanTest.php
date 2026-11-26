<?php

namespace Tests\Feature;

use App\Http\Livewire\UserManagement;
use App\Models\Tautan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InactiveOwnerTautanTest extends TestCase
{
    use RefreshDatabase;

    private function tautanFor(User $user, string $slug): Tautan
    {
        return Tautan::create([
            'user_id' => $user->id,
            'title' => 'Halaman '.$slug,
            'slug' => $slug,
            'links' => [['judul' => 'Situs', 'url' => 'https://example.com', 'active' => true]],
            'is_active' => true,
        ]);
    }

    public function test_active_owner_page_is_public(): void
    {
        $this->tautanFor(User::factory()->create(), 'pemilik-aktif');

        $this->get('/pemilik-aktif')->assertOk()->assertSee('Halaman pemilik-aktif');
    }

    public function test_deactivating_a_user_takes_their_pages_offline_until_reactivated(): void
    {
        $owner = User::factory()->create();
        $this->tautanFor($owner, 'akan-dinonaktifkan');
        $this->get('/akan-dinonaktifkan')->assertOk();

        $this->actingAs(User::factory()->create(['role' => 'admin']));

        Livewire::test(UserManagement::class)->call('deactivateUser', $owner->id);
        $this->assertSame('inactive', $owner->fresh()->status);

        auth()->logout();
        $this->get('/akan-dinonaktifkan')->assertNotFound()->assertDontSee('Halaman akan-dinonaktifkan');

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        Livewire::test(UserManagement::class)->call('activateUser', $owner->id);

        auth()->logout();
        $this->get('/akan-dinonaktifkan')->assertOk()->assertSee('Halaman akan-dinonaktifkan');
    }

    public function test_pages_of_inactive_or_disabled_owners_are_hidden_whatever_the_link_status(): void
    {
        foreach (['inactive', 'disabled'] as $status) {
            // Status set outside the deactivate action, so tautans.status is still "active".
            $owner = User::factory()->create(['status' => $status]);
            $this->tautanFor($owner, 'pemilik-'.$status);

            $this->get('/pemilik-'.$status)->assertNotFound();
        }
    }
}
