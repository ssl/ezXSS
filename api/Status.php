<?php
class StatusController extends Controller
{
    /**
     * Web-reachable actions.
     */
    public static $routable = array('index');

    /**
     * Report the current instance state.
     */
    public function index()
    {

        $theme = 'classic';
        $signup = false;
        $signupCode = false;
        try {
            $theme = (string) Setting::get('theme', 'classic');
            $signup = Setting::enabled('signup_enabled');

            $signupCode = $signup && (string) Setting::get('signup_mode', 'public') === 'code';
        } catch (Exception $e) {
            $theme = 'classic';
            $signup = false;
            $signupCode = false;
        }

        return $this->respond(array(
            'state'                 => AppState::status(),
            'version'               => Config::get('VERSION', '5.0'),
            'theme'                 => $theme,
            'signup'                => $signup,
            'signup_code_required'  => $signupCode,
        ));
    }
}
