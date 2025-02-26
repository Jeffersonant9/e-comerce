<?php
session_start();

$conn = new mysqli("localhost", "root", "", "loja");
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}


function sanitizeInput($data, $filter = FILTER_SANITIZE_FULL_SPECIAL_CHARS, $options = []) {
    return filter_input(INPUT_POST, $data, $filter, $options);
}


if (isset($_POST['cadastrar_produto'])) {
    $nome = sanitizeInput('nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $preco = sanitizeInput('preco', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);

  
    if ($nome && $preco > 0) {
        $stmt = $conn->prepare("INSERT INTO produtos (nome, preco) VALUES (?, ?)");
        $stmt->bind_param("sd", $nome, $preco);
        if ($stmt->execute()) {
            $mensagem = "Produto cadastrado com sucesso!";
        } else {
            $mensagem = "Erro ao cadastrar o produto: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $mensagem = "Dados inválidos. Verifique os valores do produto.";
    }
}


if (isset($_POST['adicionar_carrinho'])) {
    $produto_id = $_POST['adicionar_carrinho']; 

  
    $id = filter_input(INPUT_POST, "id_produto_$produto_id", FILTER_SANITIZE_NUMBER_INT);
    $nome = $_POST["nome_produto_$produto_id"];
    $preco = filter_input(INPUT_POST, "preco_produto_$produto_id", FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);


    if ($id && $nome && $preco > 0) {
        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }
        $_SESSION['carrinho'][] = [
            "id" => $id,
            "nome" => $nome,
            "preco" => $preco
        ];
    } else {
        echo "Dados inválidos. Verifique os valores do produto.";
    }
}

if (isset($_POST['remover'])) {
    $index = sanitizeInput('index', FILTER_SANITIZE_NUMBER_INT);
    if (isset($_SESSION['carrinho'][$index])) {
        unset($_SESSION['carrinho'][$index]);
        $_SESSION['carrinho'] = array_values($_SESSION['carrinho']);
    }
}

if (isset($_POST['finalizar_compra'])) {
    if (!empty($_SESSION['carrinho'])) {
    
        $total = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $total += $item['preco'];
        }


        $stmt = $conn->prepare("INSERT INTO pedidos (total) VALUES (?)");
        $stmt->bind_param("d", $total);
        if ($stmt->execute()) {
            $pedido_id = $stmt->insert_id;

        
            $stmt_itens = $conn->prepare("INSERT INTO itens_pedido (pedido_id, produto_id, preco) VALUES (?, ?, ?)");
            foreach ($_SESSION['carrinho'] as $item) {
                $produto_id = $item['id'];
                $preco_item = $item['preco'];
                $stmt_itens->bind_param("iid", $pedido_id, $produto_id, $preco_item);
                $stmt_itens->execute();
            }

          
            $_SESSION['carrinho'] = [];
            $mensagem = "Compra finalizada com sucesso! Pedido #$pedido_id.";
        } else {
            $mensagem = "Erro ao finalizar a compra: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $mensagem = "O carrinho está vazio. Adicione produtos antes de finalizar a compra.";
    }
}


$produtos = $conn->query("SELECT * FROM produtos");
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <nav class="blue darken-3 p-4">
        <div class="container flex justify-between items-center">
            <a href="#" class="text-white text-lg font-bold">Sistema de Compras</a>
        </div>
    </nav>

    <div class="container mx-auto mt-10">
        <h2 class="text-3xl font-bold text-center mb-6">Cadastrar Novo Produto</h2>
        <form method="post" class="bg-white p-6 rounded shadow-md">
            <label for="nome" class="block text-lg">Nome do Produto:</label>
            <input type="text" name="nome" id="nome" required class="w-full p-2 border rounded mb-4">
            <label for="preco" class="block text-lg">Preço do Produto:</label>
            <input type="number" name="preco" id="preco" step="0.01" required class="w-full p-2 border rounded mb-4">
            <button type="submit" name="cadastrar_produto" class="btn blue darken-3 w-full">Cadastrar Produto</button>
        </form>
    </div>

    <div class="container mx-auto mt-10">
        <h2 class="text-3xl font-bold text-center mb-6">Produtos Disponíveis</h2>
        <form method="post">
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php while ($produto = $produtos->fetch_assoc()) { ?>
                    <div class="card">
                        <div class="card-content">
                            <span class="card-title"><?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <p>R$ <?php echo number_format($produto['preco'], 2, ',', '.'); ?></p>
                            <button type="submit" name="adicionar_carrinho" value="<?php echo $produto['id']; ?>" class="btn blue darken-3 w-full">Adicionar ao Carrinho</button>
                            <input type="hidden" name="id_produto_<?php echo $produto['id']; ?>" value="<?php echo $produto['id']; ?>">
                            <input type="hidden" name="nome_produto_<?php echo $produto['id']; ?>" value="<?php echo htmlspecialchars($produto['nome'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="preco_produto_<?php echo $produto['id']; ?>" value="<?php echo $produto['preco']; ?>">
                        </div>
                    </div>
                <?php } ?>
            </div>
        </form>
    </div>

    <div class="container mx-auto mt-10">
        <h2 class="text-3xl font-bold text-center mb-6">Carrinho de Compras</h2>
        <form method="post" class="bg-white p-6 rounded shadow-md">
            <?php if (!empty($_SESSION['carrinho'])) { ?>
                <ul>
                    <?php foreach ($_SESSION['carrinho'] as $index => $item) { ?>
                        <li class="flex justify-between items-center border-b p-2">
                            <span><?php echo htmlspecialchars($item['nome'], ENT_QUOTES, 'UTF-8'); ?> - R$ <?php echo number_format($item['preco'], 2, ',', '.'); ?></span>
                            <button type="submit" name="remover" class="btn red darken-3">Remover</button>
                            <input type="hidden" name="index" value="<?php echo $index; ?>">
                        </li>
                    <?php } ?>
                </ul>
                <button type="submit" name="finalizar_compra" class="btn green darken-3 w-full mt-4">Finalizar Compra</button>
            <?php } else { ?>
                <p class="text-center">O carrinho está vazio.</p>
            <?php } ?>
        </form>
    </div>

    <?php if (isset($mensagem)) { ?>
        <p class="text-center text-red-500 mt-4"><?php echo $mensagem; ?></p>
    <?php } ?>

    <footer class="blue darken-3 p-4 text-white text-center mt-10">
        <p>&copy; 2025 Sistema de Compras. Todos os direitos reservados.</p>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>
