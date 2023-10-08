<?php

/**
 * Plugin Name: Meu Plugin de Contato
 * Description: Um plugin de formulário de contato com shortcode.
 * Version: 1.0.2
 * Author: Edvar da Luz Oliveira
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

class MeuPluginContato
{

    private $flag = true;



    public function register()
    {
        // Registre os hooks de ativação e desativação
        register_activation_hook(__FILE__, array($this, 'ativar'));
        register_deactivation_hook(__FILE__, array($this, 'desativar'));
        register_uninstall_hook(__FILE__, array($this, 'desinstalar'));

        // Registre o shortcode
        add_shortcode('formulario-contato', array($this, 'shortcode_formulario_contato'));

        // Registre o AJAX handler
        add_action('wp_ajax_enviar_contato', array($this, 'processar_formulario_contato'));
        add_action('wp_ajax_nopriv_enviar_contato', array($this, 'processar_formulario_contato'));

        // Crie a tabela no banco de dados
        //add_action('init', array($this, 'criar_tabela_contato')); // Verifique se o método está definido na classe

        // Adicione os scripts e estilos necessários
        //add_action('wp_enqueue_scripts', array($this, 'adicionar_scripts_estilos'));

        // Registre o menu de administração
        add_action('admin_menu', array($this, 'adicionar_menu_admin'));

        // Registre os estilos e scripts
        add_action('admin_enqueue_scripts', array($this, 'adicionar_scripts_estilos'));

        // Registre a ação do WordPress para excluir a mensagem
        add_action('wp_ajax_excluir_mensagem', array($this, 'excluir_mensagem'));
        add_action('wp_ajax_nopriv_excluir_mensagem', array($this, 'excluir_mensagem'));

        // Registre ação para enviar e-mail após o armazenamento da mensagem
        // add_action('mensagem_armazenada', array($this, 'enviar_email_apos_armazenamento'));

    }

    public function ativar()
    {
        // Ações a serem executadas na ativação do plugin
        global $wpdb;
        $table_name = $wpdb->prefix . 'contato';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            nome varchar(100) NOT NULL,
            email varchar(100) NOT NULL,
            telefone varchar(20),
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function desativar()
    {
        // Ações a serem executadas na desativação do plugin
        $this->flag = false;
    }

    public function desinstalar()
    {
        // Ações a serem executadas na desativação do plugin
        global $wpdb;
        $table_name = $wpdb->prefix . 'contato';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
    }

    public function adicionar_menu_admin()
    {
        add_menu_page(
            'Listar Mensagens',
            'Listar Mensagens',
            'manage_options',
            'listar-mensagens',
            array($this, 'pagina_listar_mensagens')
        );
    }

    public function adicionar_scripts_estilos($hook)
    {
        if ($hook !== 'toplevel_page_listar-mensagens') {
            return;
        }
        wp_enqueue_script('jquery');
        wp_enqueue_script('datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), '1.11.6', true);
        wp_enqueue_style('datatables-style', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css');
        wp_enqueue_style('custom-css', plugins_url('css/custom.css', __FILE__));
    }

    public function excluir_mensagem()
    {
        // Verifique se o usuário está logado (para evitar a exclusão por não logados)
        if (!is_user_logged_in()) {
            wp_send_json_error('Você não tem permissão para excluir mensagens.');
        }

        // Verifique o nonce CSRF (adicionado para segurança)
        // $nonce = $_POST['nonce'];
        // if (!wp_verify_nonce($nonce, 'excluir_mensagem_nonce')) {
        //     wp_send_json_error('Ação não autorizada.');
        // }

        // Obtenha o ID da mensagem a ser excluída
        $messageId = intval($_POST['messageId']);

        // Verifique se o ID da mensagem é válido
        if ($messageId <= 0) {
            wp_send_json_error('ID de mensagem inválido.');
        }

        // Exclua a mensagem do banco de dados
        global $wpdb;
        $table_name = $wpdb->prefix . 'contato';
        $result = $wpdb->delete($table_name, array('id' => $messageId), array('%d'));

        if ($result === false) {
            wp_send_json_error('Erro ao excluir a mensagem.');
        }

        wp_send_json_success('success'); // Envie um sucesso JSON de volta ao AJAX
    }

    public function pagina_listar_mensagens()
    {
        echo '<div class="wrap">';
        echo '<h1>Listar Mensagens</h1>';
        echo '<table id="mensagens-table" class="display" style="width:100%">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>ID</th>';
        echo '<th>Nome</th>';
        echo '<th>E-mail</th>';
        echo '<th>Telefone</th>';
        echo '<th>Data de Criação</th>';
        echo '<th>Data de Atualização</th>';
        echo '<th>Ações</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        global $wpdb;
        $table_name = $wpdb->prefix . 'contato';
        $mensagens = $wpdb->get_results("SELECT * FROM $table_name");

        foreach ($mensagens as $mensagem) {
            echo '<tr>';
            echo '<td>' . $mensagem->id . '</td>';
            echo '<td>' . $mensagem->nome . '</td>';
            echo '<td>' . $mensagem->email . '</td>';
            echo '<td>' . $mensagem->telefone . '</td>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($mensagem->created_at)) . '</td>';
            echo '<td>' . date('d/m/Y H:i:s', strtotime($mensagem->updated_at)) . '</td>';
            echo '<td><button class="delete-button" data-id="' . $mensagem->id . '" data-nonce="' . wp_create_nonce('excluir_mensagem_nonce') . '">Deletar</button></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '<script>
        jQuery(document).ready(function($) {
            $("#mensagens-table").DataTable();

            // Script para exclusão de item
            $(".delete-button").on("click", function() {
                var messageId = $(this).data("id");
                var nonce = $(this).data("nonce");
                if (confirm("Tem certeza de que deseja excluir esta mensagem?")) {
                    $.ajax({
                        type: "POST",
                        url: ajaxurl,
                        data: {
                            action: "excluir_mensagem",
                            nonce: nonce,
                            messageId: messageId
                        },
                        success: function(response) {
                            if (response.success) {
                                alert("Mensagem com ID " + messageId + " excluída com sucesso.");
                                // Recarregue a página ou atualize a tabela de mensagens aqui
                                location.reload();
                            } else {
                                alert("Ocorreu um erro ao excluir a mensagem: " + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            alert("Ocorreu um erro ao excluir a mensagem: " + error);
                        }
                    });
                }
            });
        });
    </script>';
    }



    public function criar_formulario_contato()
    {
        // Código para criar o formulário de contato
        ob_start();
?>
        <style>
            /* Estilize os campos de entrada */
            input[type="text"],
            input[type="email"],
            input[type="tel"] {
                border: 2px solid #007BFF;
                padding: 10px;
                margin-bottom: 10px;
                width: 100%;
            }

            /* Estilize o botão */
            input[type="submit"] {
                background-color: #007BFF;
                color: white;
                border: none;
                padding: 10px 60px;
                text-align: center;
                cursor: pointer;
            }

            .formulario-contato {
                text-align: center;
            }

            /* Estilo para centralizar o botão submit usando Flexbox */
            .form-submit-container {
                display: flex;
                justify-content: center;
                /* Centraliza horizontalmente */
                align-items: center;
                /* Centraliza verticalmente */
                margin-top: 10px;
                /* Espaçamento superior opcional */
            }

            /* Estilo para a mensagem de sucesso */
            .success-message {
                background-color: #28A745;
                /* Cor de fundo azul */
                color: #ffffff;
                /* Cor do texto branca */
                padding: 10px;
                /* Espaçamento interno */
                text-align: center;
                font-size: medium;
                /* Alinhamento ao centro */
                border-radius: 5px;
                /* Borda arredondada */
                margin-top: 10px;
                /* Espaçamento superior */
            }

            .error-message {
                background-color: #DC3545;
                /* Cor de fundo azul */
                color: #ffffff;
                /* Cor do texto branca */
                padding: 10px;
                /* Espaçamento interno */
                text-align: center;
                font-size: medium;
                /* Alinhamento ao centro */
                border-radius: 5px;
                /* Borda arredondada */
                margin-top: 10px;
                /* Espaçamento superior */
            }
        </style>

        <div id="formulario-contato">
            <form id="form-contato" action="" method="post">
                <input type="hidden" name="action" value="enviar_contato">

                <label for="nome">Nome:</label>
                <input type="text" name="nome" required><br>

                <label for="email">E-mail:</label>
                <input type="email" name="email" required><br>

                <label for="telefone">Telefone:</label>
                <input type="tel" name="telefone"><br>

                <!-- Campos ocultos para created_at e updated_at -->
                <input type="hidden" name="created_at" id="created_at" value="">
                <input type="hidden" name="updated_at" id="updated_at" value="">
                <div class="form-submit-container">
                    <input type="submit" value="Enviar">
                </div>
            </form>

            <div id="resposta-contato"></div>
        </div>

        <script>
            jQuery(document).ready(function($) {
                $('#form-contato').on('submit', function(e) {
                    e.preventDefault();

                    // Defina os valores para created_at e updated_at
                    var now = new Date().toISOString();
                    $('#created_at').val(now);
                    $('#updated_at').val(now);

                    var formData = $(this).serialize();
                    $.ajax({
                        type: 'POST',
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        data: formData,
                        success: function(response) {
                            $('#formulario-contato').html(response);
                        }
                    });
                });
            });
        </script>
<?php
        return ob_get_clean();
    }

    public function shortcode_formulario_contato()
    {
        // Função para exibir o formulário de contato usando shortcode
        if (!($this->flag)) {
            return null;
        }
        return $this->criar_formulario_contato();
    }

    // public function enviar_email_apos_armazenamento($nome, $email, $telefone)
    // {
    //     // Envie um e-mail com as informações
    //     $destinatario = 'contato@edvar.pro.br';
    //     $assunto = 'Nova mensagem de contato';
    //     $mensagem = "Nome: $nome\n";
    //     $mensagem .= "E-mail: $email\n";
    //     $mensagem .= "Telefone: $telefone\n";
    //     $headers = array('Content-Type: text/html; charset=UTF-8');

    //     // // Envie o e-mail
    //     // $enviado = wp_mail($destinatario, $assunto, $mensagem, $headers);

    //     if (!$enviado) {
    //         // Trate erros no envio do e-mail, se necessário
    //     }
    // }


    public function processar_formulario_contato()
    {
        // Função para processar o formulário de contato
        if (isset($_POST['action']) && $_POST['action'] == 'enviar_contato') {
            #check_admin_referer('csrf_nonce'); // Verifica o nonce CSRF

            $nome = sanitize_text_field($_POST['nome']);
            $email = sanitize_email($_POST['email']);
            $telefone = sanitize_text_field($_POST['telefone']);
            $created_at = sanitize_text_field($_POST['created_at']);
            $updated_at = sanitize_text_field($_POST['updated_at']);

            if (!empty($nome) && !empty($email)) {
                // Salvar os dados no banco de dados
                global $wpdb;
                $table_name = $wpdb->prefix . 'contato';
                $wpdb->insert(
                    $table_name,
                    array(
                        'nome' => $nome,
                        'email' => $email,
                        'telefone' => $telefone,
                        'created_at' => $created_at,
                        'updated_at' => $updated_at,
                    )
                );

                echo '<div class="success-message">Mensagem enviada com sucesso!</div>';

                // Acione a ação para enviar e-mail após o armazenamento da mensagem
                // do_action('mensagem_armazenada', $nome, $email, $telefone);
            } else {
                echo '<div class="error-message">Preencha todos os campos obrigatórios.</div>';
            }

            die(); // Encerra a execução após a resposta AJAX
        }
    }
}

if (class_exists('MeuPluginContato')) {
    // Instancie a classe do plugin
    $aut = new MeuPluginContato();
    $aut->register();
}
