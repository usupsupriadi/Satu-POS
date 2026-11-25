<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /**
     * Cache disimpan di container aplikasi (bukan static class) supaya hidup
     * hanya selama satu siklus aplikasi/request. Sebelumnya setiap pemanggilan
     * tetap menjalankan query exists() ke database.
     */
    private const CACHE_KEY = 'settings.current';

    protected static function booted(): void
    {
        static::saved(static function (): void {
            self::forgetCurrent();
        });
        static::deleted(static function (): void {
            self::forgetCurrent();
        });
    }

    /**
     * Buang instance cache supaya current() membaca ulang dari database.
     */
    public static function forgetCurrent(): void
    {
        if (app()->bound(self::CACHE_KEY)) {
            app()->forgetInstance(self::CACHE_KEY);
        }
    }

    protected $fillable = [
        'store_name',
        'phone',
        'address',
        'store_logo',
        'cashier_receipt_print_logo',
        'payment_gateway_enabled',
        'shift_required_for_pos',
        'tax_rate',
        'rounding_base',
        'point_earning_rate',
        'point_redemption_value',
        'min_redemption_points',
        'discount_applies_before_tax',
        'pos_default_customer_name',
        'pos_default_payment_method',
        'voucher_alert_days_before_expiry',
        'voucher_alert_quota_threshold',
        'corrections_void_pending_requires_approval',
        'corrections_refund_requires_approval_for_cash',
        'corrections_refund_quick_max_amount',
        'corrections_refund_quick_max_count_per_day',
        'corrections_void_quick_max_count_per_day',
        'corrections_void_quick_window_minutes',
    ];

    protected function casts(): array
    {
        return [
            'payment_gateway_enabled' => 'boolean',
            'shift_required_for_pos' => 'boolean',
            'tax_rate' => 'decimal:2',
            'rounding_base' => 'integer',
            'point_earning_rate' => 'decimal:4',
            'point_redemption_value' => 'decimal:2',
            'min_redemption_points' => 'integer',
            'discount_applies_before_tax' => 'boolean',
            'cashier_receipt_print_logo' => 'boolean',
            'voucher_alert_days_before_expiry' => 'integer',
            'voucher_alert_quota_threshold' => 'integer',
            'corrections_void_pending_requires_approval' => 'boolean',
            'corrections_refund_requires_approval_for_cash' => 'boolean',
            'corrections_refund_quick_max_amount' => 'integer',
            'corrections_refund_quick_max_count_per_day' => 'integer',
            'corrections_void_quick_max_count_per_day' => 'integer',
            'corrections_void_quick_window_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        if (app()->bound(self::CACHE_KEY)) {
            return app()->make(self::CACHE_KEY);
        }

        $setting = self::query()->first();

        if (! $setting) {
            $setting = self::query()->create([
                'store_name' => (string) config('app.name'),
                'phone' => null,
                'address' => null,
                'store_logo' => null,
                'cashier_receipt_print_logo' => true,
                'payment_gateway_enabled' => true,
                'shift_required_for_pos' => false,
                'tax_rate' => 0,
                'rounding_base' => 100,
                'point_earning_rate' => 0,
                'point_redemption_value' => 0,
                'min_redemption_points' => 0,
                'discount_applies_before_tax' => true,
                'pos_default_customer_name' => 'Walk-in',
                'pos_default_payment_method' => 'cash',
                'voucher_alert_days_before_expiry' => 7,
                'voucher_alert_quota_threshold' => 10,
                'corrections_void_pending_requires_approval' => false,
                'corrections_refund_requires_approval_for_cash' => true,
                'corrections_refund_quick_max_amount' => 20000,
                'corrections_refund_quick_max_count_per_day' => 2,
                'corrections_void_quick_max_count_per_day' => 3,
                'corrections_void_quick_window_minutes' => 5,
            ]);
        }

        app()->instance(self::CACHE_KEY, $setting);

        return $setting;
    }
}
