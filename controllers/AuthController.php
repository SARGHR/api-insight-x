<?php
class AuthController {
    public function doLogin() {
        $login = getenv('ADMIN_USER') ?: 'admin_api';
        $hash = getenv('ADMIN_PASSWORD_HASH') ?: '';
        $valid = isset($_POST['login'], $_POST['passwd']) && $hash !== ''
            && hash_equals($login, (string)$_POST['login'])
            && password_verify((string)$_POST['passwd'], $hash);
        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_logged'] = true;
            $_SESSION['admin_login'] = $login;
            return ['success'=>true, 'message'=>'Connexion réussie.', 'type'=>'alert-success'];
        }
        return ['success'=>false, 'message'=>'Identifiants incorrects ou administrateur non configuré.', 'type'=>'alert-error'];
    }
    public function doLogout() {
        $_SESSION = [];
        session_destroy();
    }
    public function isLogged() {
        return !empty($_SESSION['admin_logged']);
    }
}
