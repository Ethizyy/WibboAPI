<?php
namespace App\Controller\Utils;

use App\Controller\DefaultController;
use App\Models\Forgot;
use App\Models\User;
use Slim\Http\Request;
use Slim\Http\Response;
use App\Helper\Utils;
use Exception;

class ForgotController extends DefaultController
{
    private int $timeExpire = 48 * 60 * 60;

    public function getForgot(Request $request, Response $response, array $args): Response
    {
        $htmlText = file_get_contents('Mail/forgot.html');
        $htmlText = str_replace('{{url}}', 'https://wibbo.org', $htmlText);
        $htmlText = str_replace('{{username}}', 'Jason', $htmlText);
        $htmlText = str_replace('{{email}}', 'contact@jason.be', $htmlText);

        return $this->jsonResponse($response, [$htmlText]);
    }

    public function verifForgot(Request $request, Response $response, array $args): Response
    {
        if (empty($args['code'])) {
            throw new Exception('mail.code-invalid', 400);
        }

        $forgot = Forgot::where('pass', $args['code'])->first();
        if (!$forgot) {
            throw new Exception('mail.code-invalid', 400);
        }

        $forgotExpire = $forgot->expire + $this->timeExpire;

        if ($forgotExpire < time()) {
            Forgot::where('pass', $args['code'])->delete();
            throw new Exception('mail.expirer', 400);
        }

        $mdp = Utils::generateHash(10);
        $newpassword = md5($mdp);

        $htmlText = "Salut " . $forgot->users . ", votre nouveau mot de passe est: " . $mdp . " s'il vous plaît changer le après VOTRE connection sur le site.";

        if ($this->mail->sendMail($forgot->email, $htmlText, 'Ton nouveau mot de passe sur Wibbo!')) {
            User::where('username', $forgot->users)->update([
                'password' => $newpassword,
            ]);

            Forgot::where('pass', $args['code'])->delete();

        } else {
            throw new Exception('error', 400);
        }

        return $this->jsonResponse($response, []);
    }

    public function postForgot(Request $request, Response $response, array $args): Response
    {
        $input = $request->getParsedBody();
        $data = json_decode(json_encode($input), false);

        $this->requireData($data, ['username', 'email']);

        $email_check = preg_match("/^[a-z0-9_\.-]+@([a-z0-9]+([\-]+[a-z0-9]+)*\.)+[a-z]{2,7}$/i", $data->email);
        if (strlen($data->email) < 6 || $email_check !== 1) {
            throw new Exception('mail.invalid', 400);
        }

        $forgot = Forgot::where('users', $data->username)->where('email', $data->email)->first();
        if ($forgot) {
            $forgot_expire = $forgot->expire + $this->timeExpire;
            if ($forgot_expire > time()) {
                throw new Exception('mail.in-progress', 400);
            }
            Forgot::where('users', $data->username)->where('email', $data->email)->delete();
        }

        $user = User::where('username', $data->username)->where('mail', $data->email)->where('mail_valide', '1')->first();
        if (!$user) {
            throw new Exception('mail.deny', 400);
        }

        $code = md5(Utils::generateHash(10));

        $username = $data->username;
        $email = $data->email;
        $url = "https://wibbo.org/forgot/" . $code;
        $sujet = "Mot de passe oublier";

        $htmlText = file_get_contents('Mail/forgot.html');
        $htmlText = str_replace('{{url}}', $url, $htmlText);
        $htmlText = str_replace('{{username}}', $username, $htmlText);
        $htmlText = str_replace('{{email}}', $email, $htmlText);

        if ($this->mail->sendMail($email, $htmlText, $sujet, true)) {
            Forgot::insert([
                'pass' => $code,
                'users' => $data->username,
                'email' => $data->email,
                'expire' => time(),
            ]);
        } else {
            throw new Exception('error', 400);
        }

        return $this->jsonResponse($response, []);
    }
}
