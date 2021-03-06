<?php

namespace core\controllers;

use core\classes\Mail;
use core\classes\Store;
use core\models\Client;
use core\models\Product;
use Exception;

class Cart
{
    public function addToCart()
    {
        if (!isset($_GET['id_pdt'])) {
            echo $_SESSION['cart'] ?? 0;
            return;
        }

        $idPdt      = $_GET['id_pdt'];
        $cart       = array();
        $product    = new Product();
        $results    = $product->validateStockProduct($idPdt);

        if (!$results) {
            echo $_SESSION['cart'] ?? 0;
            return;
        }

        if (isset($_SESSION['cart'])) {
            $cart = $_SESSION['cart'];
        }

        if (key_exists($idPdt, $cart)) {
            $cart[$idPdt] ++;
        } else {
            $cart[$idPdt] = 1;
        }

        $_SESSION['cart'] = $cart;

        $totalPdt = 0;
        foreach ($cart as $pdt) {
            $totalPdt += $pdt;
        }

        echo $totalPdt;
    }

    public function cleanCart()
    {
        unset ($_SESSION['cart']);
        Store::redirect('carrinho');
    }

    public function cart()
    {
        if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
            $data = ['cart' => null];
        } else {
            $ids = array();
            foreach ($_SESSION['cart'] as $id => $qtd) {
                $ids[] = $id;
            }

            $ids = implode(',', $ids);

            $product = new Product();
            $results = $product->searchProductsIds($ids);

            $data = array();
            foreach ($_SESSION['cart'] as $idPdt => $qtdTmp) {
                foreach ($results as $product) {
                    if ($product->id_pdt == $idPdt) {

                        $idPdt = $product->id_pdt;
                        $image = $product->imagem_pdt;
                        $title = $product->nome_pdt;
                        $qtd   = $qtdTmp;
                        $price = $product->preco_pdt * $qtd;

                        $data[] = [
                            'id'    => $idPdt,
                            'image' => $image,
                            'title' => $title,
                            'qtd'   => $qtd,
                            'price' => $price
                        ];
                        break;
                    }
                }
            }

            $totalPrice = 0;

            foreach ($data as $item) {
                $totalPrice += $item['price'];
            }

            $_SESSION['totalCart'] = $totalPrice;
            $data['total'] = $totalPrice;
            $data = ['cart' => $data];
        }

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'carrinho.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    public function removeProduct()
    {
        $idPdt = $_GET['idPdt'];
        $cart  = $_SESSION['cart'];
        unset($cart[$idPdt]);
        $_SESSION['cart'] = $cart;
        Store::redirect('carrinho');
    }

    public function finishOrder()
    {
        if (!Store::isClientLogged()) {
            $_SESSION['tmpCart'] = true;
            Store::redirect('login');
        } else {
            Store::redirect('finalizar_pedido_resumo');
        }
    }

    public function finishOrderResume()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
            Store::redirect('inicio');
            return;
        }

        $ids = array();
        foreach ($_SESSION['cart'] as $id => $qtd) {
            $ids[] = $id;
        }

        $ids = implode(',', $ids);

        $product = new Product();
        $results = $product->searchProductsIds($ids);

        $cart = array();
        foreach ($_SESSION['cart'] as $idPdt => $qtdTmp) {
            foreach ($results as $product) {
                if ($product->id_pdt == $idPdt) {

                    $idPdt = $product->id_pdt;
                    $image = $product->imagem_pdt;
                    $title = $product->nome_pdt;
                    $qtd   = $qtdTmp;
                    $price = $product->preco_pdt * $qtd;

                    $cart[] = [
                        'id'    => $idPdt,
                        'image' => $image,
                        'title' => $title,
                        'qtd'   => $qtd,
                        'price' => $price
                    ];
                    break;
                }
            }
        }

        $totalPrice = 0;

        foreach ($cart as $item) {
            $totalPrice += $item['price'];
        }

        $client = new Client();
        $clientData = $client->searchClient($_SESSION['client']);

        $data = [
            'cart'  => $cart,
            'client'=> $clientData,
            'total' => $totalPrice
        ];

        if (!isset($_SESSION['orderCode'])) {
            $orderCode = Store::generateOrderCode();
            $_SESSION['orderCode'] = $orderCode;
        }

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'carrinho_resumo.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    /**
     * @throws Exception
     */
    public function confirmOrder()
    {
        if (!Store::isClientLogged()) {
            Store::redirect('inicio');
            return;
        }

        if (!isset($_SESSION['cart']) || count($_SESSION['cart']) == 0) {
            Store::redirect('inicio');
            return;
        }

        $ids = array();
        foreach ($_SESSION['cart'] as $idProduto => $quantidade) {
            $ids[] = $idProduto;
        }
        $ids = implode(',', $ids);
        $produto = new Product();
        $products = $produto->searchProductsIds($ids);

        $strPdt = array();
        foreach ($products as $product) {
            $qtd = $_SESSION['cart'][$product->id_pdt];
            $strPdt[] = $qtd . 'X ' . $product->nome_pdt . ' R$ ' . number_format($product->preco_pdt, 2, ',','.') . ' /unidade';
        }

        $mailData = [
            'produtos'  => $strPdt,
            'total'     => 'R$ ' . number_format($_SESSION['totalCart'], 2, ',', '.'),
            'pagamento' => [
                'pix'       => '123456789',
                'orderCode' => $_SESSION['orderCode'],
            ],
        ];

        $mail = new Mail();
        $mail->sendEmailOrderConfirmed($_SESSION['email'], $mailData);

        $cdOrder = $_SESSION['orderCode'];
        $totalOrder = $_SESSION['totalCart'];

        $data = [
            'cdOrder'    => $cdOrder,
            'totalOrder' => $totalOrder,
        ];

        $order = new Orders();
        $clientCtrl = new Client();
        $clientData = $clientCtrl->searchClient($_SESSION['client']);

        $endereco = array();
        $client = array();

        if (isset($_SESSION['dados_alternativos']['endereco']) && !empty($_SESSION['dados_alternativos']['endereco'])) {
        $endereco['endereco'] = $_SESSION['dados_alternativos']['endereco'];
        } else {
            $endereco['endereco'] = $clientData->endereco_cliente;
        }

        if (isset($_SESSION['dados_alternativos']['cidade']) && !empty($_SESSION['dados_alternativos']['cidade'])) {
            $endereco['cidade'] = $_SESSION['dados_alternativos']['cidade'];
        } else {
            $endereco['cidade'] = $clientData->cidade_cliente;
        }

        if (isset($_SESSION['dados_alternativos']['email']) && !empty($_SESSION['dados_alternativos']['email'])) {
            $client['email'] = $_SESSION['dados_alternativos']['email'];
        } else {
            $client['email'] = $clientData->email_cliente;
        }

        if (isset($_SESSION['dados_alternativos']['telefone']) && !empty($_SESSION['dados_alternativos']['telefone'])) {
            $client['telefone'] = $_SESSION['dados_alternativos']['telefone'];
        } else {
            $client['telefone'] = $clientData->telefone_cliente;
        }

        $orderData = [
            'id_cliente'  => $_SESSION['client'],
            'endereco'    => $endereco['endereco'],
            'cidade'      => $endereco['cidade'],
            'email'       => $client['email'],
            'telefone'    => $client['telefone'],
            'cod_pedido'  => $_SESSION['orderCode'],
            'status'      => ORDER_PENDENTE,
            'msg'         => ''
        ];

        $productsData = array();

        foreach ($products as $product) {
            $productsData[] = [
                'nome'          => $product->nome_pdt,
                'valorUnd'      => $product->preco_pdt,
                'quantidade'    => $_SESSION['cart'][$product->id_pdt]
            ];
        }

        $order->saveOrder($orderData, $productsData);

        unset($_SESSION['orderCode']);
        unset($_SESSION['cart']);
        unset($_SESSION['totalCart']);
        unset($_SESSION['dados_alternativos']);

        Store::layout([
            'layouts/html_header.php',
            'layouts/header.php',
            'confirmar_pedido.php',
            'layouts/footer.php',
            'layouts/html_footer.html'
        ], $data);
    }

    public function alternativeData()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $_SESSION['dados_alternativos'] = [
            'endereco'  => $data['endereco'],
            'cidade'    => $data['cidade'],
            'email'     => $data['email'],
            'telefone'  => $data['telefone']
        ];
    }
}