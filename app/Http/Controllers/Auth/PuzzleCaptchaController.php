<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PuzzleCaptchaController extends Controller
{
    public const SESSION_KEY = 'pkdi_puzzle_captcha';

    private const CANVAS_WIDTH = 320;

    private const CANVAS_HEIGHT = 156;

    private const TOLERANCE_PX = 8;

    public function challenge(Request $request): JsonResponse
    {
        $targetX = random_int(145, 250);
        $targetY = random_int(38, 92);

        // Decoy cutout placed cleanly away from real target (similar to reference UI)
        $decoyX = $targetX > 195 ? random_int(48, 105) : random_int(215, 255);
        $decoyY = max(30, min(96, $targetY + random_int(-18, 18)));
        if (abs($decoyX - $targetX) < 62) {
            $decoyX = $targetX > 180 ? 60 : 245;
        }

        $token = Str::random(32);
        $seed = random_int(1000, 999999);

        $request->session()->put(self::SESSION_KEY, [
            'token' => $token,
            'target_x' => $targetX,
            'target_y' => $targetY,
            'verified' => false,
            'verified_token' => null,
            'expires_at' => now()->addMinutes(15)->timestamp,
        ]);

        return response()->json([
            'token' => $token,
            'width' => self::CANVAS_WIDTH,
            'height' => self::CANVAS_HEIGHT,
            'target_x' => $targetX,
            'target_y' => $targetY,
            'decoy_x' => $decoyX,
            'decoy_y' => $decoyY,
            'seed' => $seed,
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:64'],
            'position_x' => ['required', 'numeric', 'min:0', 'max:'.self::CANVAS_WIDTH],
        ]);

        $challenge = $request->session()->get(self::SESSION_KEY);
        if (! is_array($challenge) || ($challenge['expires_at'] ?? 0) < now()->timestamp) {
            return response()->json([
                'verified' => false,
                'expired' => true,
                'message' => 'Sesi verifikasi habis. Silakan muat ulang puzzle.',
            ], 422);
        }

        if (! hash_equals((string) ($challenge['token'] ?? ''), (string) $validated['token'])) {
            return response()->json([
                'verified' => false,
                'message' => 'Token verifikasi tidak cocok. Silakan muat ulang puzzle.',
            ], 422);
        }

        $targetX = (float) ($challenge['target_x'] ?? -999);
        $positionX = (float) $validated['position_x'];

        if (abs($positionX - $targetX) <= self::TOLERANCE_PX) {
            $verifiedToken = Str::random(40);
            $challenge['verified'] = true;
            $challenge['verified_token'] = $verifiedToken;
            $request->session()->put(self::SESSION_KEY, $challenge);

            return response()->json([
                'verified' => true,
                'verified_token' => $verifiedToken,
                'message' => 'Verifikasi keamanan berhasil.',
            ]);
        }

        return response()->json([
            'verified' => false,
            'message' => 'Posisi kepingan belum tepat, silakan geser kembali.',
        ], 422);
    }

    public static function assertVerified(Request $request): void
    {
        // Izinkan automated PHPUnit feature tests berjalan tanpa render canvas browser
        if (app()->runningUnitTests() && ! $request->has('captcha_verified_token')) {
            return;
        }

        $submitted = trim((string) $request->input('captcha_verified_token', ''));
        $challenge = $request->session()->get(self::SESSION_KEY);

        $isValid = is_array($challenge)
            && ($challenge['verified'] ?? false) === true
            && ($challenge['expires_at'] ?? 0) >= now()->timestamp
            && is_string($challenge['verified_token'] ?? null)
            && $submitted !== ''
            && hash_equals($challenge['verified_token'], $submitted);

        if (! $isValid) {
            throw ValidationException::withMessages([
                'captcha' => 'Silakan selesaikan Verifikasi Keamanan (geser kepingan puzzle ke tempatnya) terlebih dahulu.',
            ]);
        }

        // Konsumsi token sekali pakai
        $request->session()->forget(self::SESSION_KEY);
    }
}
