<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use DB;
use App\Models\User;
use App\Models\Consortium;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {
            public function toResponse($request)
            {
                $user = auth()->user();
                $user->last_login = now();
                $user->save();
                // Set session and redirect
                if ($user->hasRole('ServerAdmin')) {
                    return redirect()->intended('server/home');
                } else if ($user->hasRole('Admin')) {
                    return redirect()->intended('/adminHome');
                } else {
                    return redirect()->intended('/home');
                }
            }
        });

        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            public function toResponse($request)
            {
                session(['ccp_con_key' => '']);
                $request->session()->invalidate();
                return redirect('/login');
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(0)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        Fortify::authenticateUsing(function (Request $request) {
            // Get and/or set consortium key; server admin allowed to login with choosing one
            $conso_key = (isset($request->consortium)) ? $request->consortium : null;
            if (is_null($conso_key) || $conso_key == '') {
                if ($request->email == config('ccplus.server_admin')) {
                    $request->session()->regenerate();
                    session(['ccp_con_key' => "con_template"]);
                    $conso_key = "con_template";
                } else {
                    throw ValidationException::withMessages([
                        Fortify::username() => ['A consortium selection is required'],
                    ]);
                }                                    
            } else {
                $request->session()->regenerate();
                session(['ccp_con_key' => $conso_key]);
            }

            // Set consodb based on key.
            config(['database.connections.consodb.database' => 'ccplus_' . $conso_key]);

            // Connect the database and move on the to next request
             try {
                 DB::reconnect();
             } catch (\Exception $e) {
                 Storage::append('reconnect_fails.log', date('Y-m-d H:is') . ' : Reconnect attempt failed! Path: ' .
                                 $request->getPathInfo());
                 throw ValidationException::withMessages([
                     Fortify::username() => ['Failed to connect to consortium database'],
                 ]);
             }
            // Check credentials
            $user = User::where('email', $request->email)->first();
            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }
        });
    }
}
