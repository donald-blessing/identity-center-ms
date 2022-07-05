<?php

namespace App\Api\V1\Controllers\Public;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Identification;
use App\Http\Controllers\Controller;
use App\Models\TwoFactorSecurity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TwoFASecurityController extends Controller
{
    /**
     * Show 2FA Setting form
     */
    public function show2faForm(Request $request){
        try {
            $user = Auth::user();
            $google2fa_url = "";
            $secret_key = "";

            if($user->loginSecurity()->exists()){
                $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());
                $google2fa_url = $google2fa->getQRCodeInline(
                    env('APP_NAME'),
                    $user->phone,
                    $user->loginSecurity->google2fa_secret
                );
                $secret_key = $user->loginSecurity->google2fa_secret;
            }

            $data = array(
                'user' => $user,
                'secret' => $secret_key,
                'google2fa_url' => $google2fa_url
            );

            return response()->jsonApi([
                "type" => "danger",
                "message" => "Image generated",
                "data" => $data
            ], 200);
        } catch (\Throwable $th) {
            return response()->jsonApi([
                "type" => "danger",
                "message" => $th->getMessage(),
                "data" => null
            ], 500);
        }

    }

    /**
     * Generate 2FA secret key
     */
    public function generate2faSecret(Request $request){
        try {
            $user = Auth::user();
            // Initialise the 2FA class
            $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());

            // Add the secret key to the registration data
            $login_security = TwoFactorSecurity::where('user_id', $user->id)->first();
            $login_security->user_id = $user->id;
            $login_security->google2fa_enable = 0;
            $login_security->google2fa_secret = $google2fa->generateSecretKey();
            $login_security->save();

            return response()->jsonApi([
                "type" => "danger",
                "message" => "Secret key is generated.",
                "data" => $login_security
            ], 200);
        } catch (\Throwable $th) {
            return response()->jsonApi([
                "type" => "danger",
                "message" => $th->getMessage(),
                "data" => null
            ], 500);
        }
    }

    /**
     * Enable 2FA
     */
    public function enable2fa(Request $request){
        $this->validate($request, [
            'secret' => 'required'
        ]);
        $user = Auth::user();
        $google2fa = (new \PragmaRX\Google2FAQRCode\Google2FA());

        $secret = $request->secret;
        $valid = $google2fa->verifyKey($user->loginSecurity->google2fa_secret, $secret);

        if($valid){
            $user->loginSecurity->google2fa_enable = 1;
            $user->loginSecurity->save();
            return response()->jsonApi([
                "type" => "danger",
                "message" => '2FA is enabled successfully',
                "data" => null
            ], 200);
        }else{
            return response()->jsonApi([
                "type" => "danger",
                "message" => 'Invalid verification Code, Please try again.',
                "data" => null
            ], 500);
        }
    }

    /**
     * Disable 2FA
     */
    public function disable2fa(Request $request){

        try {
            if (!(Hash::check($request->get('current-password'), Auth::user()->password))) {
                // The passwords matches
                return response()->jsonApi([
                    "type" => "danger",
                    "message" => 'Your password does not matches with your account password. Please try again.',
                    "data" => null
                ], 403);
            }

            $this->validate($request, [
                'current-password' => 'required'
            ]);

            $user = Auth::user();
            $user->loginSecurity->google2fa_enable = 0;
            $user->loginSecurity->save();

            return response()->jsonApi([
                "type" => "success",
                "message" => '2FA is now disabled.',
                "data" => null
            ], 200);

        } catch (\Throwable $th) {
            return response()->jsonApi([
                "type" => "success",
                "message" => $th->getMessage(),
                "data" => null
            ], 403);
        }
    }
}
