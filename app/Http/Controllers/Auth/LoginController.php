<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use App\Utils\BusinessUtil;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * All Utils instance.
     */
    protected $businessUtil;

    protected $moduleUtil;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(BusinessUtil $businessUtil, ModuleUtil $moduleUtil)
    {
        $this->middleware('guest')->except('logout');

        // Do not use throttle middleware here,
        // because it shows default 429 page before controller can return custom message.

        $this->businessUtil = $businessUtil;
        $this->moduleUtil = $moduleUtil;
    }

    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Login field is username instead of email.
     *
     * @return string
     */
    public function username()
    {
        return 'username';
    }

    /**
     * Maximum login attempts before lockout.
     *
     * @return int
     */
    public function maxAttempts()
    {
        return 5;
    }

    /**
     * Lockout time in minutes.
     *
     * @return int
     */
    public function decayMinutes()
    {
        return 1;
    }

    /**
     * Custom response when login is locked.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function sendLockoutResponse(Request $request)
    {
        $seconds = $this->limiter()->availableIn(
            $this->throttleKey($request)
        );

        return redirect()
            ->back()
            ->withInput($request->except('password'))
            ->with('status', [
                'success' => 0,
                'msg' => 'Too many login attempts. Please try again in ' . $seconds . ' seconds.',
            ])
            ->with('login_lockout_seconds', $seconds);
    }

    public function logout()
    {
        $this->businessUtil->activityLog(auth()->user(), 'logout');

        request()->session()->flush();
        \Auth::logout();

        return redirect('/login');
    }

    /**
     * The user has been authenticated.
     * Check if the business is active or not.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  mixed  $user
     * @return mixed
     */
    protected function authenticated(Request $request, $user)
    {
        $this->businessUtil->activityLog($user, 'login', null, [], false, $user->business_id);

        if (! $user->business->is_active) {
            \Auth::logout();

            return redirect('/login')
                ->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.business_inactive'),
                ]);
        } elseif ($user->status != 'active') {
            \Auth::logout();

            return redirect('/login')
                ->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.user_inactive'),
                ]);
        } elseif (! $user->allow_login) {
            \Auth::logout();

            return redirect('/login')
                ->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.login_not_allowed'),
                ]);
        } elseif (
            ($user->user_type == 'user_customer')
            && ! $this->moduleUtil->hasThePermissionInSubscription($user->business_id, 'crm_module')
        ) {
            \Auth::logout();

            return redirect('/login')
                ->with('status', [
                    'success' => 0,
                    'msg' => __('lang_v1.business_dont_have_crm_subscription'),
                ]);
        }
    }

    protected function redirectTo()
    {
        $user = \Auth::user();
        if (! $user->can('dashboard.data') && $user->can('sell.create')) {
            return '/pos/create';
        }

        if ($user->user_type == 'user_customer') {
            return 'contact/contact-dashboard';
        }

        return '/home';
    }
}