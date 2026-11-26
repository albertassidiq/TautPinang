<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Tautan extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tautans';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'slug',
        'links',
        'styles', // JSON styles system with RGBA support
        'custom_styles', // ✅ BARU: Custom styles that override global styles
        'logo_url',
        'judul_gambar', // ✅ BARU: Nama file gambar (contoh: bps-tanjungpinang.html)
        'use_uploaded_logo', // ✅ BARU: Boolean untuk tracking upload/URL
        'tujuan', // ✅ BARU: 'external' atau 'internal'
        'footer_text_1',
        'footer_text_2',
        'is_active',
    ];

    protected $casts = [
        'links' => 'array',
        'styles' => 'array', // Cast JSON to array
        'custom_styles' => 'array', // ✅ BARU: Cast custom styles to array
        'is_active' => 'boolean',
        'use_uploaded_logo' => 'boolean', // ✅ BARU: Cast ke boolean
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at',
    ];

    /**
     * Font Awesome class list only, e.g. "fa-solid fa-house" or "fab fa-instagram".
     * Icon values come from the client, so anything else is dropped.
     */
    public const ICON_CLASS_PATTERN = '/^fa[a-z0-9-]*(?: fa[a-z0-9-]*){0,5}$/D';

    public static function sanitizeIconClass($icon): string
    {
        if (!is_string($icon)) {
            return '';
        }

        $icon = trim($icon);

        if (strlen($icon) > 100 || !preg_match(self::ICON_CLASS_PATTERN, $icon)) {
            return '';
        }

        return $icon;
    }

    /**
     * Normalize the icon of every link before it is stored.
     */
    public static function sanitizeLinkIcons(array $links): array
    {
        return array_map(function ($link) {
            if (is_array($link) && array_key_exists('icon', $link)) {
                $link['icon'] = self::sanitizeIconClass($link['icon']);
            }

            return $link;
        }, $links);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Pages that may be shown publicly: the page itself is active and its
     * owner's account is active. The owner's status is checked directly so a
     * page goes offline however the account was deactivated.
     */
    public function scopePubliclyVisible($query)
    {
        return $query->where('is_active', true)
            ->whereHas('user', function ($owner) {
                $owner->where('status', 'active');
            });
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    // ✅ BARU: Scope untuk filter berdasarkan tujuan
    public function scopeExternal($query)
    {
        return $query->where('tujuan', 'external');
    }

    public function scopeInternal($query)
    {
        return $query->where('tujuan', 'internal');
    }

    // ✅ BARU: Scope untuk filter berdasarkan penggunaan gambar
    public function scopeWithUploadedImage($query)
    {
        return $query->where('use_uploaded_logo', true);
    }

    public function scopeWithUrlImage($query)
    {
        return $query->where('use_uploaded_logo', false);
    }

    // Accessors & Mutators
    public function getLinksCountAttribute()
    {
        return is_array($this->links) ? count($this->links) : 0;
    }

    public function getCreatedDateAttribute()
    {
        return $this->created_at->format('d M Y H:i');
    }

    // ✅ BARU: Accessor untuk mengetahui apakah menggunakan gambar upload
    public function getHasUploadedImageAttribute()
    {
        return $this->use_uploaded_logo && !empty($this->judul_gambar);
    }

    // ✅ BARU: Accessor untuk mendapatkan nama file HTML
    public function getHtmlFilenameAttribute()
    {
        return $this->slug . '.html';
    }

    // ✅ BARU: Accessor untuk mendapatkan nama file gambar yang seharusnya
    public function getExpectedImageFilenameAttribute()
    {
        if (!$this->use_uploaded_logo) {
            return null;
        }

        // Format: slug-logo.extension (misal: bps-tanjungpinang-logo.png)
        return $this->judul_gambar;
    }

    // ✅ BARU: Method untuk menentukan apakah perlu generate instruksi gambar
    public function needsImageInstructions()
    {
        return $this->use_uploaded_logo && !empty($this->judul_gambar);
    }

    // Accessor for logo URL with fallback
    public function getLogoUrlAttribute($value)
    {
        return $value ?: 'https://i.pinimg.com/474x/e4/8a/b2/e48ab2e2e8055f15f04caf6968c1314a.jpg';
    }

    // Updated: Accessor for styles with RGBA-based fallback
    public function getStylesAttribute($value)
    {
        if (empty($value)) {
            return $this->getDefaultRgbaStyles();
        }

        $styles = json_decode($value, true);

        // If we have old hex-based styles, convert them to RGBA
        if ($this->hasOldStyleFormat($styles)) {
            return $this->convertHexToRgbaStyles($styles);
        }

        return $styles;
    }

    // Helper method to get GitHub Pages URL
    public function getGithubUrlAttribute()
    {
        return "https://bps2172.github.io/TautPinang/{$this->slug}.html";
    }

    // Helper method to check if has valid links
    public function hasValidLinks()
    {
        if (!is_array($this->links)) {
            return false;
        }

        foreach ($this->links as $link) {
            if (!empty($link['judul']) && !empty($link['url'])) {
                return true;
            }
        }

        return false;
    }

    // Get CSS-ready styles for rendering (with custom styles override)
    public function getCssStyles()
    {
        $styles = $this->styles ?: $this->getDefaultRgbaStyles();

        // Apply custom styles override if exists
        if (!empty($this->custom_styles)) {
            $styles = $this->applyCustomStyles($styles, $this->custom_styles);
        }

        return [
            'background' => [
                'gradient' => ($styles['background']['gradientStart'] ?? 'rgba(0,35,102,1)') . ',' . ($styles['background']['gradientEnd'] ?? 'rgba(29,78,95,1)'),
                'direction' => ($styles['background']['direction'] ?? '135') . 'deg',
            ],
            'container' => [
                'backgroundColor' => $styles['container']['backgroundColor'] ?? 'rgba(255,255,255,0.95)',
                'borderRadius' => ($styles['container']['borderRadius'] ?? '24') . 'px',
                'backdropFilter' => 'blur(' . ($styles['container']['backdropBlur'] ?? '20') . 'px)',
                'topGradient' => ($styles['container']['topGradientStart'] ?? 'rgba(255,215,0,1)') . ',' . ($styles['container']['topGradientEnd'] ?? 'rgba(0,35,102,1)') . ',' . ($styles['container']['topGradientStart'] ?? 'rgba(255,215,0,1)'),
                'topGradientHeight' => ($styles['container']['topGradientHeight'] ?? '4') . 'px',
            ],
            'title' => [
                'fontSize' => ($styles['title']['fontSize'] ?? '26') . 'px',
                'fontWeight' => $styles['title']['fontWeight'] ?? '700',
                'color' => $styles['title']['color'] ?? 'rgba(0,35,102,1)',
            ],
            'description' => [
                'color' => $styles['description']['color'] ?? 'rgba(102,102,102,1)',
                'fontSize' => ($styles['description']['fontSize'] ?? '15') . 'px',
            ],
            'button' => [
                'backgroundColor' => $styles['button']['backgroundColor'] ?? 'rgba(255,255,255,1)',
                'color' => $styles['button']['color'] ?? 'rgba(0,35,102,1)',
                'borderRadius' => ($styles['button']['borderRadius'] ?? '12') . 'px',
                'borderColor' => $styles['button']['borderColor'] ?? 'rgba(234,234,234,1)',
                'borderWidth' => ($styles['button']['borderWidth'] ?? '2') . 'px',
                'borderStyle' => $styles['button']['borderStyle'] ?? 'solid',
            ],
            'buttonHover' => [
                'background' => 'linear-gradient(135deg, ' . ($styles['buttonHover']['backgroundStart'] ?? 'rgba(255,215,0,1)') . ', ' . ($styles['buttonHover']['backgroundEnd'] ?? 'rgba(255,255,255,1)') . ')',
                'color' => $styles['buttonHover']['color'] ?? 'rgba(0,35,102,1)',
                'glowColor' => $styles['buttonHover']['glowColor'] ?? 'rgba(255,215,0,0.3)',
                'glowSize' => '0 ' . ($styles['buttonHover']['glowBlur'] ?? '30') . 'px ' . ($styles['buttonHover']['glowBlur'] ?? '30') . 'px',
                'borderColor' => $styles['buttonHover']['borderColor'] ?? 'rgba(255,215,0,1)',
                'borderWidth' => ($styles['buttonHover']['borderWidth'] ?? '2') . 'px',
                'borderStyle' => $styles['buttonHover']['borderStyle'] ?? 'solid',
            ],
            'logo' => [
                'borderColor' => $styles['logo']['borderColor'] ?? 'rgba(255,215,0,1)',
                'borderWidth' => ($styles['logo']['borderWidth'] ?? '3') . 'px',
                'borderStyle' => $styles['logo']['borderStyle'] ?? 'solid',
            ],
            'animations' => [
                'fadeInDuration' => ($styles['animations']['fadeInDuration'] ?? '0.6') . 's',
                'hoverDuration' => ($styles['animations']['hoverDuration'] ?? '0.3') . 's',
            ],
            'qrcode' => [
                'enabled' => $styles['qrcode']['enabled'] ?? true,
                'position' => $styles['qrcode']['position'] ?? 'bottom-right',
                'size' => ($styles['qrcode']['size'] ?? '200') . 'px',
                'borderRadius' => ($styles['qrcode']['borderRadius'] ?? '12') . 'px',
                'padding' => ($styles['qrcode']['padding'] ?? '16') . 'px',
                'backgroundColor' => $styles['qrcode']['backgroundColor'] ?? '#ffffff',
                'borderColor' => $styles['qrcode']['borderColor'] ?? '#ffffff',
                'darkColor' => $styles['qrcode']['darkColor'] ?? '#000000',
                'shadow' => $styles['qrcode']['shadow'] ?? true,
                'tooltipText' => $styles['qrcode']['tooltipText'] ?? 'Scan QR untuk buka halaman ini',
                'showOnMobile' => $styles['qrcode']['showOnMobile'] ?? false,
                'hoverEffect' => $styles['qrcode']['hoverEffect'] ?? true
            ],
        ];
    }

    // Private helper methods untuk styles (tetap sama seperti sebelumnya)
    private function getDefaultRgbaStyles()
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
                'backgroundColor' => '#ffffff',    // Background QR - PUTIH
                'borderColor' => '#ffffff',        // Border Container - PUTIH
                'darkColor' => '#000000'           // Pattern QR - HITAM
            ]
        ];
    }

    private function hasOldStyleFormat($styles)
    {
        if (!is_array($styles)) return false;

        if (isset($styles['background']['gradient']) && strpos($styles['background']['gradient'], ',') !== false) {
            return true;
        }

        if (isset($styles['title']['color']) && strpos($styles['title']['color'], '#') === 0) {
            return true;
        }

        return false;
    }

    private function convertHexToRgbaStyles($oldStyles)
    {
        // Convert hex colors to RGBA but preserve existing RGBA data
        $styles = $oldStyles;

        // Convert background gradient if needed
        if (isset($styles['background']['gradient']) && strpos($styles['background']['gradient'], ',') !== false) {
            // Keep existing gradient format - no conversion needed
        }

        // Convert title color if hex
        if (isset($styles['title']['color']) && strpos($styles['title']['color'], '#') === 0) {
            $styles['title']['color'] = $this->hexToRgba($styles['title']['color']);
        }

        // Preserve ALL existing data including QR code settings
        // Don't override anything that's already set

        return $styles;
    }

    private function hexToRgba($hex, $alpha = 1)
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if (strlen($hex) == 6) {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));

            return "rgba($r, $g, $b, $alpha)";
        }

        return "rgba(0, 0, 0, $alpha)";
    }

    /**
     * Apply custom styles that override global styles
     */
    private function applyCustomStyles($globalStyles, $customStyles)
    {
        // Deep merge - custom styles override global styles
        return array_replace_recursive($globalStyles, $customStyles);
    }

    /**
     * Check if tautan has custom styles
     */
    public function hasCustomStyles()
    {
        return !empty($this->custom_styles) && is_array($this->custom_styles);
    }

    /**
     * Get custom styles as JSON
     */
    public function getCustomStylesJson()
    {
        return $this->custom_styles ? json_encode($this->custom_styles, JSON_PRETTY_PRINT) : '{}';
    }
}
