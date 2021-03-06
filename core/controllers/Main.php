<?php

namespace core\controllers;

use core\classes\Mail;
use core\classes\Store;
use core\models\Client;
use core\models\Product;
use Exception;

class Main
{
    public function index()
    {
        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'inicio.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ]);
    }

    public function store()
    {
        $product     = new Product();
        $productList = $product->productList($_GET['c'] ?? 'todos');
        $categories  = $product->searchCategories();

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'loja.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], [
            'products'  => $productList,
            'categories'=> $categories,
        ]);
    }

    public function registerClientForm()
    {
        if (Store::isClientLogged()) {
            $this->index();
            return;
        }

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_cadastro.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ]);
    }

    /**
     * @throws Exception
     */
    public function registerClient()
    {
        if (Store::isClientLogged()) {
            $this->index();
            return;
        }

        //valida se foi feito uma requisição diferente de post
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->index();
            return;
        }

        //verifica senhas
        if ($_POST['cliente_senha1'] !== $_POST['cliente_senha2']) {
            $_SESSION['error'] = 'As senhas não são iguais!';
            $this->registerClient();
            return;
        }

        $client = new Client();

        if ($client->validateEmail($_POST['cliente_email'])) {
            $_SESSION['error'] = 'E-mail já cadastrado na base de dados!';
            $this->registerClient();
            return;
        }

        $purl = $client->insertClient();

        //envia o e-mail para o cliente
        $email = new Mail();
        $sendEmail = $email->sendEmailRegisterConfirm(strtolower(trim($_POST['cliente_email'])), $purl);

        if ($sendEmail) {

            Store::layout([
                'layouts/html_header.php',
                'layouts/header.php',
                'cliente_cadastro_sucesso.php',
                'layouts/footer.php',
                'layouts/html_footer.html'
            ]);
            return;

        } else {
            echo 'aconteceu um erro';
        }

    }

    public function confirmMail()
    {
        if (Store::isClientLogged()) {
            $this->index();
            return;
        }

        //verifica se veio purl
        if (!isset($_GET['purl'])) {
            $this->index();
            return;
        }

        //valida o purl valido
        if (strlen($_GET['purl']) != 32) {
            $this->index();
            return;
        }

        $client = new Client();
        $clientConfirm = $client->validateRegister($_GET['purl']);

        if ($clientConfirm) {

            Store::layout([
                'layouts/html_header.php',
                'layouts/header.php',
                'cliente_cadastro_confirmado.php',
                'layouts/footer.php',
                'layouts/html_footer.html'
            ]);
            return;

        } else {
            Store::redirect('inicio');
        }
    }

    public function login()
    {
        if (Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_login.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ]);
    }

    public function loginSubmit()
    {
        if (Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            Store::redirect('inicio');
            return;
        }

        if (
            !isset($_POST['user_email'])
            || !isset($_POST['user_pass'])
            || !filter_var(trim($_POST['user_email']), FILTER_VALIDATE_EMAIL)
        ) {
            $_SESSION['error'] = 'E-mail ou senha inválido, tente novamente';
            Store::redirect('login');
            return;
        }

        $user = (trim(strtolower($_POST['user_email'])));
        $pass = $_POST['user_pass'];

        $client = new Client();
        $isValid = $client->validateLogin($user, $pass);

        if (!$isValid) {
            $_SESSION['error'] = 'Login Inválido';
            Store::redirect('login');
            return;
        }

        $_SESSION['client']     = $isValid->id_cliente;
        $_SESSION['email']      = $isValid->email_cliente;
        $_SESSION['clientName'] = $isValid->nome_cliente;

        if (isset($_SESSION['tmpCart'])) {
            unset($_SESSION['tmpCart']);
            Store::redirect('finalizar_pedido_resumo');
        } else {
            Store::redirect('inicio');
        }
    }

    public function logout()
    {
        unset ($_SESSION['client']);
        unset ($_SESSION['email']);
        unset ($_SESSION['clientName']);

        Store::redirect('inicio');
    }

    public function perfil()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        $client = new Client();

        $dtemp = $client->searchClient($_SESSION['client']);

        $data = [
            'clientData'  => [
                'Email'     => $dtemp->email_cliente,
                'Nome'      => $dtemp->nome_cliente,
                'Endereço'  => $dtemp->endereco_cliente,
                'Cidade'    => $dtemp->cidade_cliente,
                'Telefone'  => $dtemp->telefone_cliente,
            ]
        ];

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_perfil.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    /**
     * @throws Exception
     */
    public function alterPersonalData()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        $client = new Client();

        $data = [
            'personalData' => $client->searchClient($_SESSION['client']),
        ];

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_alterar_dados_pessoais.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    public function alterPersonalDataSubmit()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            Store::redirect('inicio');
            return;
        }

        $email = trim(strtolower($_POST['email']));
        $nome = trim($_POST['nome']);
        $endereco = trim($_POST['endereco']);
        $cidade = trim($_POST['cidade']);
        $telefone = trim($_POST['telefone']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || empty($email)) {
            $_SESSION['error'] = 'E-mail inválido!';
            $this->alterPersonalData();
        }

        if (empty($nome) || empty($endereco) || empty($cidade)) {
            $_SESSION['error'] = 'Preencha os dados corretamente!';
            $this->alterPersonalData();
        }

        $client = new Client();
        $result = $client->validateEmailNotInUse($email);

        if ($result) {
            $_SESSION['error'] = 'E-mail já cadastrado!';
            $this->alterPersonalData();
        }

        $client->updateClient($email, $nome, $endereco, $cidade, $telefone);

        $_SESSION['email']      = $email;
        $_SESSION['clientName'] = $nome;

        Store::redirect('perfil');

    }

    public function alterPassword()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_alterar_senha.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ]);
    }

    public function alterPasswordSubmit()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            Store::redirect('inicio');
            return;
        }

        $atualPass = trim($_POST['senhaAtual']);
        $newPass1 = trim($_POST['senhaNova1']);
        $newPass2 = trim($_POST['senhaNova2']);

        if (strlen($newPass1) < 8) {
            $_SESSION['error'] = 'A senha deve conter no mínimo 8 caracteres!';
            $this->alterPassword();
            return;
        }

        $client = new Client();
        $pass = $client->validatePassword($atualPass);

        if (!$pass) {
            $_SESSION['error'] = 'A senha atual está errada!';
            $this->alterPassword();
            return;
        }

        if ($newPass1 != $newPass2) {
            $_SESSION['error'] = 'A senha mova inserida não bate com a confirmação!';
            $this->alterPassword();
            return;
        }

        $client->updatePass($newPass1);

        Store::redirect('perfil');
    }

    /**
     * @throws Exception
     */
    public function orderHistory()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        $orders = new Orders();
        $historyOrders = $orders->searchOrders($_SESSION['client']);

        $data = [
            'historyOrder' => $historyOrders,
        ];

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_historico_pedidos.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    public function orderDetails()
    {
        if (
            !Store::isClientLogged() || !isset($_GET['id']) || strlen($_GET['id']) != 32) {
            Store::redirect('inicio');
            return;
        }

        $orderId = Store::strDecryptAes($_GET['id']);

        if (empty($orderId)) {
            Store::redirect('inicio');
            return;
        }

        $orders = new Orders();
        $order = $orders->searchOrderByClientById($_SESSION['client'], $orderId);

        if (!$order) {
            Store::redirect('inicio');
            return;
        }

        $total = null;
        foreach ($order['itens'] as $product) {
            $total += $product->quantidade * $product->valor_unitario;
        }

        $data = [
            'order' => $order['order'],
            'products' => $order['itens'],
            'total' => $total,
        ];

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'cliente_detalhe_pedido.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    /**
     * @throws Exception
     */
    public function payment()
    {
        // para testar basta fazer a rota abaixo pelo navegador
        // http://localhost/Web-Store/public/index.php?pagina=pagamento&codOrder=FG5435612

        $codOrder = '';

        if (!isset($_GET['codOrder'])) {
            return;
        } else {
            $codOrder = $_GET['codOrder'];
        }

        $orders = new Orders();
        $results = $orders->checkOrderStatus($codOrder);

        //TODO criar envio de email após trocar o status
        if ($results[0]->status_pedido == ORDER_PENDENTE) {
            $orders->setStatusPaidOut($codOrder);
            echo 'Pago';
        }

        echo 'Erro, pedido já pago ou inexistente';
    }
}