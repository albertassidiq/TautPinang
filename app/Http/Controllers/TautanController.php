<?php

namespace App\Http\Controllers;

use App\Models\Tautan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TautanController extends Controller
{
    /**
     * 📋 List semua tautan user
     */
    public function index()
    {
        $tautans = Tautan::where('user_id', Auth::id())
            ->latest()
            ->get();

        return view('tautan.index', compact('tautans'));
    }

    /**
     * ✏️ Form buat tautan baru
     */
    public function create()
    {
        return view('tautan.create');
    }

    /**
     * 💾 Simpan tautan baru
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|min:3|max:100',
            'slug' => [
                'required',
                'min:3',
                'max:50',
                'regex:/^[a-zA-Z0-9\-_]+$/',
                'unique:tautans,slug',
                function ($attribute, $value, $fail) {
                    $reserved = [
                        'dashboard',
                        'login',
                        'register',
                        'logout',
                        'password',
                        'tautan',
                        'admin',
                        'api',
                        'home',
                        'about',
                        'contact'
                    ];

                    if (in_array(strtolower($value), $reserved)) {
                        $fail('Slug tidak dapat digunakan karena sudah di-reserve.');
                    }
                }
            ],
            'description' => 'nullable|max:500',
            'links' => 'required|array|min:1',
            'links.*.judul' => 'required|min:1|max:100',
            'links.*.url' => 'required|url',
            'logo_url' => 'nullable|url',
            'styles' => 'required|array',
            'footer_text_1' => 'nullable|max:100',
            'footer_text_2' => 'nullable|max:100',
        ]);

        $tautan = Tautan::create([
            'user_id' => Auth::id(),
            'title' => $validated['title'],
            'slug' => Str::slug($validated['slug']),
            'description' => $validated['description'] ?? null,
            'links' => $validated['links'],
            'logo_url' => $validated['logo_url'] ?? 'https://i.pinimg.com/474x/e4/8a/b2/e48ab2e2e8055f15f04caf6968c1314a.jpg',
            'styles' => $validated['styles'],
            'footer_text_1' => $validated['footer_text_1'] ?? null,
            'footer_text_2' => $validated['footer_text_2'] ?? null,
            'tujuan' => 'internal',
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tautan berhasil dibuat!',
            'slug' => $tautan->slug,
            'url' => route('tautan.show', $tautan->slug),
        ]);
    }

    /**
     * 👁️ Tampilkan halaman tautan public (DYNAMIC!)
     */
    public function show($slug)
    {
        // SIMPLE CHECK: Cegah reserved slugs
        $reservedSlugs = [
            'dashboard',
            'login',
            'register',
            'logout',
            'password',
            'tautan',
            'admin',
            'api',
            'home',
            'about',
            'contact',
            'privacy',
            'terms',
            'user',
            'profile',
            'settings'
        ];

        if (in_array(strtolower($slug), $reservedSlugs)) {
            abort(404, 'Halaman tidak ditemukan.');
        }

        // Cari tautan berdasarkan slug
        $tautan = Tautan::where('slug', $slug)
            ->publiclyVisible()
            ->first();

        // Jika tautan tidak ditemukan, tampilkan custom 404 page
        if (!$tautan) {
            return $this->showCustom404($slug);
        }

        // Render HTML langsung
        return $this->renderTautanPage($tautan);
    }

    /**
     * 📝 Form edit tautan
     */
    public function edit($slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('tautan.edit', compact('tautan'));
    }

    /**
     * 📝 Form edit tautan berdasarkan ID (untuk Livewire)
     */
    public function editById($id)
    {
        $tautan = Tautan::where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('tautan.edit', compact('tautan'));
    }

    /**
     * 🔄 Update tautan
     */
    public function update(Request $request, $slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|min:3|max:100',
            'slug' => 'required|min:3|max:50|regex:/^[a-zA-Z0-9\-_]+$/|unique:tautans,slug,' . $tautan->id,
            'description' => 'nullable|max:500',
            'links' => 'required|array|min:1',
            'logo_url' => 'nullable|url',
            'styles' => 'required|array',
            'custom_styles' => 'nullable|array', // ✅ BARU: Custom styles untuk override
            'footer_text_1' => 'nullable|max:100',
            'footer_text_2' => 'nullable|max:100',
            'is_active' => 'boolean',
        ]);

        $tautan->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Tautan berhasil diupdate!',
            'url' => route('tautan.show', $tautan->slug),
        ]);
    }

    /**
     * ✨ Form edit custom styles tautan
     */
    public function editCustomStyles($slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        return view('tautan.edit-custom-styles', compact('tautan'));
    }

    /**
     * ✨ Update custom styles tautan
     */
    public function updateCustomStyles(Request $request, $slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $validated = $request->validate([
            'custom_styles' => 'nullable|array',
        ]);

        $tautan->update([
            'custom_styles' => $validated['custom_styles'] ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Custom styles berhasil diperbarui!',
            'url' => route('tautan.show', $tautan->slug),
        ]);
    }

    /**
     * 🗑️ Hapus tautan
     */
    public function destroy($slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $tautan->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tautan berhasil dihapus!',
        ]);
    }

    /**
     * 📊 Toggle active status
     */
    public function toggleActive($slug)
    {
        $tautan = Tautan::where('slug', $slug)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $tautan->update(['is_active' => !$tautan->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $tautan->is_active,
            'message' => $tautan->is_active ? 'Tautan diaktifkan!' : 'Tautan dinonaktifkan!',
        ]);
    }

    /**
     * 🧪 TEST: Tampilkan semua data tautan untuk debugging
     */
    public function testAllData()
    {
        // Ambil SEMUA tautan dari database
        $tautans = Tautan::orderBy('created_at', 'desc')->get();

        return view('tautan.test', [
            'tautans' => $tautans
        ]);
    }

    /**
     * 🎨 Render HTML untuk tautan (Private Method)
     */
    private function renderTautanPage(Tautan $tautan)
    {
        // ✅ PAKAI raw styles dari database (JSON asli) - sudah ada di $tautan->styles
        // Jika styles kosong, gunakan default
        if (empty($tautan->styles)) {
            $tautan->styles = $this->getDefaultStyles();
        }

        return view('tautan.public', [
            'tautan' => $tautan
        ]);
    }
    // Tambahkan method helper untuk default styles (SYNC dengan BuatTautan)
    protected function getDefaultStyles()
    {
        return [
            'background' => [
                'gradientStart' => '#002366',
                'gradientEnd' => '#1D4E5F',
                'direction' => '135'
            ],
            'container' => [
                'backgroundType' => 'solid',
                'backgroundColor' => '#FFFFFF',
                'backgroundGradientStart' => '#FFFFFF',
                'backgroundGradientEnd' => '#F8FAFC',
                'backgroundGradientDirection' => '135',
                'borderRadius' => '24',
                'backdropBlur' => '20',
                'topGradientStart' => '#FFD700',
                'topGradientEnd' => '#002366',
                'topGradientHeight' => '4'
            ],
            'title' => [
                'fontSize' => '26',
                'fontWeight' => '700',
                'color' => '#002366'
            ],
            'description' => [
                'color' => '#666666',
                'fontSize' => '15'
            ],
            'button' => [
                'backgroundColor' => '#FFFFFF',
                'color' => '#002366',
                'borderRadius' => '12',
                'borderColor' => '#EAEAEA',
                'borderWidth' => '2',
                'borderStyle' => 'solid'
            ],
            'buttonHover' => [
                'backgroundStart' => '#FFD700',
                'backgroundEnd' => '#FFFFFF',
                'color' => '#002366',
                'glowColor' => '#FFD700',
                'glowBlur' => '30',
                'borderColor' => '#FFD700',
                'borderWidth' => '2',
                'borderStyle' => 'solid'
            ],
            'logo' => [
                'backgroundType' => 'solid',
                'backgroundColor' => '#FFFFFF',
                'backgroundGradientStart' => '#FFFFFF',
                'backgroundGradientEnd' => '#F8FAFC',
                'backgroundGradientDirection' => '135',
                'borderColor' => '#FFD700',
                'borderWidth' => '3',
                'borderStyle' => 'solid',
                'borderRadius' => '50'
            ],
            'footer' => [
                'color' => '#999999',
                'fontSize' => '12',
                'borderColor' => '#EEEEEE',
                'marginTop' => '30',
                'paddingTop' => '20'
            ],
            'animations' => [
                'fadeInDuration' => '0.6',
                'hoverDuration' => '0.3',
                'backgroundAnimation' => [
                    'type' => 'none',
                    'speed' => '5',
                    'intensity' => '40',
                    'color1' => '#002366',
                    'color2' => '#FFD700',
                    'color3' => '#1D4E5F',
                    'direction' => '0',
                    'size' => '40'
                ]
            ],
            'qrcode' => [
                'enabled' => true,
                'position' => 'bottom-right',
                'size' => '200',
                'borderRadius' => '12',
                'padding' => '10',
                'shadow' => true,
                'hoverEffect' => true,
                'showOnMobile' => false,
                'tooltipText' => 'Scan QR untuk buka halaman ini',
                'backgroundColor' => '#ffffff',        // Background QR - PUTIH
                'borderColor' => '#ffffff',            // Border Container - PUTIH
                'darkColor' => '#000000'               // Pattern QR - HITAM
            ]
        ];
    }

    /**
     * Convert RGBA color to Hex for QR OG Image
     */
    private function rgbaToHex($color)
    {
        if (strpos($color, 'rgba') === 0) {
            // Extract RGB from rgba(r, g, b, a)
            preg_match('/rgba\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches);
            if (count($matches) >= 4) {
                $r = intval($matches[1]);
                $g = intval($matches[2]);
                $b = intval($matches[3]);
                return sprintf("#%02x%02x%02x", $r, $g, $b);
            }
        } elseif (strpos($color, 'rgb') === 0) {
            // Extract RGB from rgb(r, g, b)
            preg_match('/rgb\((\d+),\s*(\d+),\s*(\d+)/', $color, $matches);
            if (count($matches) >= 4) {
                $r = intval($matches[1]);
                $g = intval($matches[2]);
                $b = intval($matches[3]);
                return sprintf("#%02x%02x%02x", $r, $g, $b);
            }
        }

        // Return as-is if already hex or other format
        return $color;
    }

    /**
     * Generate QR Code OG Image URL for public pages
     */
    private function generateQrCodeOgImage($tautan)
    {
        $qrSettings = $tautan->styles['qrcode'] ?? [];

        // Extract QR colors
        $bgColor = $qrSettings['backgroundColor'] ?? '#ffffff';
        $darkColor = $qrSettings['darkColor'] ?? '#000000';

        // Convert RGBA to hex if needed
        $bgColorHex = $this->rgbaToHex($bgColor);
        $darkColorHex = $this->rgbaToHex($darkColor);

        // Remove # for API compatibility
        $bgColorApi = ltrim($bgColorHex, '#');
        $darkColorApi = ltrim($darkColorHex, '#');

        // Build QR Code URL for OG Image
        $qrSize = 400; // OG Image standard size
        $data = urlencode($tautan->title ?? 'Taut Pinang');

        // Use API with custom colors
        $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size={$qrSize}x{$qrSize}&data={$data}&color={$darkColorApi}&bgcolor={$bgColorApi}&margin=2";

        return $qrUrl;
    }

    /**
     * 🎨 Custom 404 Page dengan branding
     */
    private function showCustom404($slug)
    {
        // Data untuk custom 404 page
        $data = [
            'slug' => $slug,
            'logo_url' => 'https://i.pinimg.com/474x/e4/8a/b2/e48ab2e2e8055f15f04caf6968c1314a.jpg', // Logo kamu
            'title' => 'Tautan Tidak Ditemukan',
            'message' => "Tautan ini tidak ada",
            'suggestions' => [
                'Periksa kembali URL yang Anda masukkan',
                'Hubungi pemilik tautan untuk memastikan link masih aktif',
                'Buat tautan baru secara gratis di Taut Pinang'
            ],
            'back_url' => url('/dashboard'),
            'create_url' => url('/buat-tautan')
        ];

        // Return 404 HTTP status tetapi dengan custom view
        return response()->view('tautan.404', $data, 404);
    }
}
