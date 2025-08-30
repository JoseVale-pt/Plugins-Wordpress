<?php
/**
 * Plugin Name: JMV AI Tool Suggestions
 * Description: Formulário simples para recolher sugestões de ferramentas de IA 
 * e guardá-las como um tipo de conteúdo pendente, para analise e validação para incluir na lista.
 * Version: 1.0.0
 * Author: José Vale
 * License: GPL2+
 */

if (!defined('ABSPATH')) exit;

// ----- 1) Tipo de conteúdo: Sugestões -----
add_action('init', function () {
    register_post_type('ai_tool_suggestion', [
        'label' => 'Sugestões de Ferramentas IA',
        'labels' => [
            'name' => 'Sugestões de Ferramentas IA',
            'singular_name' => 'Sugestão de Ferramenta IA'
        ],
        'public' => false,
        'show_ui' => true,
        'menu_icon' => 'dashicons-lightbulb',
        'supports' => ['title', 'editor'],
    ]);
});

// ----- 2) Shortcode do formulário -----
add_shortcode('ai_tool_form', function ($atts) {
    $action = esc_url(admin_url('admin-post.php'));
    $success = isset($_GET['ai_tool_submitted']) && $_GET['ai_tool_submitted'] === '1';
    $error   = isset($_GET['ai_tool_error']) ? sanitize_text_field($_GET['ai_tool_error']) : '';

    ob_start();
    ?>
    <div class="jv-aitool-form-wrapper">
        <?php if ($success): ?>
            <div class="jv-aitool-notice jv-aitool-success">Obrigado! A tua sugestão foi recebida e ficará pendente de revisão.</div>
        <?php elseif (!empty($error)): ?>
            <div class="jv-aitool-notice jv-aitool-error"><?php echo esc_html($error); ?></div>
        <?php endif; ?>

        <form class="jv-aitool-form" action="<?php echo $action; ?>" method="post" novalidate>
            <input type="hidden" name="action" value="jv_ai_tool_submit">
            <?php wp_nonce_field('jv_ai_tool_submit', 'jv_ai_tool_nonce'); ?>

            <!-- Honeypot anti-spam (deve ficar vazio) -->
            <div style="position:absolute; left:-9999px; top:auto; width:1px; height:1px; overflow:hidden;">
                <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>

            <div class="jv-field">
                <label for="tool_name">Nome da ferramenta *</label>
                <input id="tool_name" name="tool_name" type="text" required maxlength="140" />
            </div>

            <div class="jv-field">
                <label for="tool_url">Link para a ferramenta *</label>
                <input id="tool_url" name="tool_url" type="url" required placeholder="https://..." />
            </div>

            <div class="jv-field">
                <label for="tool_desc">Descrição breve (como usas a ferramenta?) *</label>
                <textarea id="tool_desc" name="tool_desc" rows="4" required maxlength="1000"></textarea>
            </div>

            <div class="jv-field">
                <label for="tool_topics">Tópicos/áreas (separados por vírgulas) *</label>
                <input id="tool_topics" name="tool_topics" type="text" required placeholder="ex.: Escrita, Programação, Marketing" />
            </div>

            <div class="jv-field jv-consent">
                <label>
                    <input type="checkbox" name="consent" value="1" required />
                    Aceito que esta sugestão seja guardada e analisada para possível inclusão na lista.
                </label>
            </div>

            <button type="submit" class="jv-btn">Enviar sugestão</button>
        </form>
    </div>
    <?php
    jv_aitool_inline_styles();
    return ob_get_clean();
});

// ----- 3) Handler do POST -----
add_action('admin_post_nopriv_jv_ai_tool_submit', 'jv_ai_tool_handle_submit');
add_action('admin_post_jv_ai_tool_submit', 'jv_ai_tool_handle_submit');

function jv_ai_tool_handle_submit() {
    // Referer para voltar com mensagem
    $referer = wp_get_referer() ? wp_get_referer() : home_url('/');

    // Honeypot
    if (!empty($_POST['website'])) {
        wp_safe_redirect(add_query_arg('ai_tool_error', rawurlencode('Erro: suspeita de spam.'), $referer));
        exit;
    }

    // Nonce
    if (!isset($_POST['jv_ai_tool_nonce']) || !wp_verify_nonce($_POST['jv_ai_tool_nonce'], 'jv_ai_tool_submit')) {
        wp_safe_redirect(add_query_arg('ai_tool_error', rawurlencode('Sessão expirada. Tenta novamente.'), $referer));
        exit;
    }

    // Campos
    $name  = isset($_POST['tool_name']) ? sanitize_text_field($_POST['tool_name']) : '';
    $url   = isset($_POST['tool_url']) ? esc_url_raw(trim($_POST['tool_url'])) : '';
    $desc  = isset($_POST['tool_desc']) ? sanitize_textarea_field($_POST['tool_desc']) : '';
    $topics= isset($_POST['tool_topics']) ? sanitize_text_field($_POST['tool_topics']) : '';
    $consent = !empty($_POST['consent']);

    // Validação
    if (!$consent || empty($name) || empty($url) || empty($desc) || empty($topics)) {
        wp_safe_redirect(add_query_arg('ai_tool_error', rawurlencode('Preenche todos os campos obrigatórios.'), $referer));
        exit;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        wp_safe_redirect(add_query_arg('ai_tool_error', rawurlencode('O link não parece válido.'), $referer));
        exit;
    }

    // Inserir como CPT pendente
    $post_id = wp_insert_post([
        'post_type'   => 'ai_tool_suggestion',
        'post_status' => 'pending',
        'post_title'  => $name,
        'post_content'=> $desc,
        'meta_input'  => [
            '_jv_tool_url'    => $url,
            '_jv_tool_topics' => $topics,
            '_jv_tool_source' => 'front_form',
        ],
    ]);

    if (is_wp_error($post_id) || !$post_id) {
        wp_safe_redirect(add_query_arg('ai_tool_error', rawurlencode('Não foi possível guardar. Tenta outra vez.'), $referer));
        exit;
    }

    // Notificar admin
    $admin = get_option('admin_email');
    $subject = 'Nova sugestão de ferramenta IA';
    $body = "Nome: {$name}\nLink: {$url}\nTópicos: {$topics}\n\nDescrição:\n{$desc}\n\nEditar: " . admin_url("post.php?post={$post_id}&action=edit");
    @wp_mail($admin, $subject, $body);

    // Redirect com sucesso
    wp_safe_redirect(add_query_arg('ai_tool_submitted', '1', $referer));
    exit;
}

// ----- 4) Colunas no WP-Admin -----
add_filter('manage_ai_tool_suggestion_posts_columns', function ($cols) {
    $cols['jv_url'] = 'Link';
    $cols['jv_topics'] = 'Tópicos';
    return $cols;
});
add_action('manage_ai_tool_suggestion_posts_custom_column', function ($col, $post_id) {
    if ($col === 'jv_url') {
        $url = get_post_meta($post_id, '_jv_tool_url', true);
        if ($url) echo '<a href="'.esc_url($url).'" target="_blank" rel="noopener noreferrer">Abrir</a>';
    }
    if ($col === 'jv_topics') {
        echo esc_html(get_post_meta($post_id, '_jv_tool_topics', true));
    }
}, 10, 2);

// ----- 5) Estilos inline simples (podes trocar por enqueue se preferires) -----
function jv_aitool_inline_styles() { ?>
    <style>
    .jv-aitool-form-wrapper { border:1px solid #e5e7eb; padding:1rem; border-radius:0.75rem; }
    .jv-aitool-notice { padding:0.75rem; border-radius:0.5rem; margin-bottom:0.75rem; font-weight:600; }
    .jv-aitool-success { background:#ecfdf5; border:1px solid #34d399; }
    .jv-aitool-error { background:#fef2f2; border:1px solid #f87171; }
    .jv-field { margin-bottom:0.75rem; }
    .jv-field label { display:block; font-weight:600; margin-bottom:0.25rem; }
    .jv-field input[type="text"],
    .jv-field input[type="url"],
    .jv-field textarea { width:100%; padding:0.6rem; border:1px solid #d1d5db; border-radius:0.5rem; }
    .jv-consent { font-size:0.95rem; }
    .jv-btn { display:inline-block; padding:0.6rem 1rem; border-radius:0.5rem; border:0; background:#111827; color:#fff; cursor:pointer; }
    .jv-btn:hover { opacity:0.9; }
    </style>
<?php }
