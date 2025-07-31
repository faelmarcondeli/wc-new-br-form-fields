<?php
/**
 * Plugin Name: WooCommerce Novos Campos do Registro e Checkout
 * Description: Adiciona novos campos ao formulário de registro e finalização de compras
 * Version:     1.0.0
 * Author:      Rafael Moreno
 * Text Domain: wc-new-br-form-fields
 */

//NOVO FORMULARIO DE REGISTRO
// 1) Adiciona no formulário de registro
add_action( 'woocommerce_register_form_start', function() {
    // Se o usurio já existe, tentar carregar o valor salvo
    $saved = is_user_logged_in()
        ? get_user_meta( get_current_user_id(), 'billing_persontype', true )
        : '1';
    wp_enqueue_script( 'woocommerce-extra-checkout-fields-for-brazil-front' );
    wp_enqueue_style(  'woocommerce-extra-checkout-fields-for-brazil-front' );
    ?>
    
    <p class="form-row form-row-wide fl-is-required">
        <select name="billing_persontype" id="billing_persontype" required>
            <option value="2" selected <?php selected( $saved, '2' ); ?>>Pessoa Jurídica</option>
        </select>
    </p>

    <div id="pj_fields" style="<?php echo $saved === '2' ? '' : 'display:block;'; ?>">
        <p class="form-row form-row-wide fl-is-required">
            <label for="billing_cnpj" class="fl-label">CNPJ <abbr class="required">*</abbr></label>
            <input type="text" class="input-text" name="billing_cnpj" id="billing_cnpj"
                   data-bmw-mask="cnpj"
                   value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'billing_cnpj', true ) ); ?>"/>
            <span id="cnpj_error" style="color:red; display:none;"></span>
        </p>

        <p class="form-row form-row-wide">
            <label for="billing_ie" class="fl-label">Inscrição Estadual</label>
            <div style="position: relative;">
                <input type="text" class="input-text fl-input" name="billing_ie" id="billing_ie"
                       data-bmw-mask="ie"
                       value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'billing_ie', true ) ); ?>"
                       style="padding-right:90px; height:3em;"/>
                <label style="position: absolute; right: 10px; top: 50%; transform: translateY(-90%); font-size: 0.9em; display: flex; align-items: center; cursor: pointer; background: #fff; padding-left: 5px;">
        			<input type="checkbox" id="ie_isento_checkbox" style="margin-right: 5px; margin-bottom: 3px !important;" />
        			Sou isento (Não quero informar)
        		</label>
            </div>
            <span id="ie_error" style="color:red; display:none;"></span>
        </p>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        // 2) Corrige os rótulos dos campos obrigatórios
        document.querySelectorAll('.fl-wrap.fl-is-required .fl-label').forEach(function(label){
            // 3) Remove qualquer ocorrncia de "Obrigatório"
            label.innerHTML = label.innerHTML.replace(/Obrigatório\s*/i, '').trim();
        });

        // 4) Corrige os placeholders dos campos obrigatrios
        document.querySelectorAll('.fl-wrap.fl-is-required input').forEach(function(input){
            // 5) Se o placeholder contiver a palavra "Obrigatrio", remove
            input.placeholder = input.placeholder.replace(/obrigatório\s*/i, '').trim();
        });
    });
    document.addEventListener('DOMContentLoaded', function(){
        const sel = document.getElementById('billing_persontype');
        const pf = document.getElementById('pf_fields');
        const pj = document.getElementById('pj_fields');
        const cpf = document.getElementById('billing_cpf');
        const cnpj = document.getElementById('billing_cnpj');
        const ie  = document.getElementById('billing_ie');
        const cb  = document.getElementById('ie_isento_checkbox');
        const err = {
            cpf: document.getElementById('cpf_error'),
            cnpj: document.getElementById('cnpj_error'),
            ie:  document.getElementById('ie_error')
        };

        sel.addEventListener('change', ()=> {
            if(sel.value==='2'){
                pf.style.display='none';
                pj.style.display='block';
            } else {
                pf.style.display='block';
                pj.style.display='none';
            }
        });

        cb.addEventListener('change', ()=>{
            if(cb.checked){
                ie.value='ISENTO';
                ie.readOnly=true;
                err.ie.style.display='none';
            } else {
                ie.readOnly=false;
                ie.value='';
            }
        });

        const validate = (type, val, el)=> {
            fetch('<?php echo admin_url("admin-ajax.php");?>',{
                method:'POST',
                headers:{'Content-Type':'application/x-www-form-urlencoded'},
                body:`action=validate_brazilian_field&type=${type}&value=${encodeURIComponent(val)}`
            }).then(r=>r.json()).then(d=>{
                if(d.valid) el.style.display='none';
                else {
                    el.textContent=d.message;
                    el.style.display='block';
                }
            });
        };

        [ ['cpf',cpf,err.cpf],
          ['cnpj',cnpj,err.cnpj]
        ].forEach(([t,input,e])=>{
            input.addEventListener('blur', ()=>{
                const v=input.value.trim();
                if(v!=='') validate(t,v,e);
                else e.style.display='none';
            });
        });

        ie.addEventListener('blur', ()=>{
            const v=ie.value.trim();
            if(!cb.checked && v!=='') validate('ie',v,err.ie);
            else err.ie.style.display='none';
        });
    });
    
    document.addEventListener('DOMContentLoaded', function () {
    const inputs = document.querySelectorAll('.woocommerce #customer_login input, .woocommerce #billing_fields input, .woocommerce #shipping_fields input, .woocommerce-page input');

    inputs.forEach(input => {
        // Ignora o campo de senha
        if (input.type !== 'password') {
            // Verifica se o campo  o de email (ID 'reg_email'), e se for, mantm em minúsculas
            if (input.id === 'reg_email' || input.id === 'reg_email_confirm' || input.id === 'username') {
                input.addEventListener('input', function () {
                    let value = input.value;
                    input.value = value.toLowerCase(); // Sempre em minsculas
                });
            } else {
                // Para os outros campos, aplica a transformaço de versalete (primeira letra maiúscula)
                input.addEventListener('input', function () {
                    let value = input.value;
                    // Transforma tudo em minúsculas
                    value = value.toLowerCase();
                    // Aplica maiscula na primeira letra de cada palavra
                    value = value.replace(/\b\w/g, char => char.toUpperCase());
                    input.value = value;
                    });
                }
            }
        });
    });

    
    </script>
    <?php
});

// 2) AJAX de validacao
add_action( 'wp_ajax_validate_brazilian_field', 'validate_brazilian_field_ajax' );
add_action( 'wp_ajax_nopriv_validate_brazilian_field','validate_brazilian_field_ajax' );
function validate_brazilian_field_ajax(){
    require_once WP_PLUGIN_DIR.'/woocommerce-extra-checkout-fields-for-brazil/includes/class-extra-checkout-fields-for-brazil-formatting.php';
    $type  = sanitize_text_field($_POST['type']  ?? '');
    $value = sanitize_text_field($_POST['value'] ?? '');
    $valid = false; $msg = '';
    switch($type){
        case 'cpf':
            $valid = Extra_Checkout_Fields_For_Brazil_Formatting::is_cpf($value);
            $msg   = $valid?'':'CPF invlido';
            break;
        case 'cnpj':
            $valid = Extra_Checkout_Fields_For_Brazil_Formatting::is_cnpj($value);
            $msg   = $valid?'':'CNPJ inválido';
            break;
        case 'ie':
            $valid = strtoupper($value)==='ISENTO' || preg_match('/^\d+$/',$value);
            $msg   = $valid?'':'Inscrição Estadual inválida';
            break;
        default:
            $msg='Tipo invlido';
    }
    wp_send_json(['valid'=>$valid,'message'=>$msg]);
}

// 3) Validaço no servidor
add_filter('woocommerce_registration_errors', function($errors,$user,$email){
    require_once WP_PLUGIN_DIR.'/woocommerce-extra-checkout-fields-for-brazil/includes/class-extra-checkout-fields-for-brazil-formatting.php';
    // persontype
    $pt = sanitize_text_field($_POST['billing_persontype'] ?? '');
    if(!in_array($pt,['1','2'],true)){
        $errors->add('billing_persontype','Selecione o tipo de pessoa');
    }
    // CPF ou CNPJ + IE
    if($pt==='1'){
        $cpf= sanitize_text_field($_POST['billing_cpf'] ?? '');
        if(empty($cpf) || !Extra_Checkout_Fields_For_Brazil_Formatting::is_cpf($cpf)){
            $errors->add('billing_cpf','CPF inválido');
        }
    }
    if($pt==='2'){
        $cnpj= sanitize_text_field($_POST['billing_cnpj'] ?? '');
        if(empty($cnpj) || !Extra_Checkout_Fields_For_Brazil_Formatting::is_cnpj($cnpj)){
            $errors->add('billing_cnpj','CNPJ inválido');
        }
        $ie = sanitize_text_field($_POST['billing_ie'] ?? '');
        if($ie!=='' && strtoupper($ie)!=='ISENTO' && !preg_match('/^\d+$/',$ie)){
            $errors->add('billing_ie','Inscriço Estadual inválida');
        }
    }
    return $errors;
},10,3);

// 4) Salva no user_meta
add_action('woocommerce_created_customer', function($cust_id){
    // persontype + campos
    $map = [
        'billing_persontype','billing_cpf','billing_cnpj','billing_ie'
    ];
    foreach($map as $f){
        if(isset($_POST[$f])){
            update_user_meta($cust_id,$f,sanitize_text_field($_POST[$f]));
        }
    }
    
});


// NEW REGISTER_FIELDS
function woo_extra_register_fields() {
   if( is_account_page() || is_checkout() ) {
    wp_enqueue_script( 'woocommerce-extra-checkout-fields-for-brazil-front' );
    wp_enqueue_script( 'valid_checkout_fields' );
    ?> 

  function make_readonly() {
      ?>
      <script type='text/javascript'>
      jQuery(function($){
          // Para cada campo, verifica se tem valor e define como readonly
          $('input#billing_email, input#account_email, input#billing_company, input#billing_cnpj, input#billing_cpf, input#billing_ie, input#account_display_name').each(function(){
              if ($(this).val()) {
                  $(this).prop('readonly', true);
                  $(this).css({'background-color': '#f1f1f1','touch-action': 'none','box-shadow': 'unset'
          });
    // Adicionando uma cor de fundo para indicar que o campo  somente leitura
              }
          });
      });
      </script>
      <?php
  }

    <p class="form-row form-row-first" id="billing_first_name_field">
    <label for="billing_first_name"><?php _e( 'First name', 'woocommerce' ); ?><span class="required">*</span></label>
    <input type="text" class="input-text fl-input" name="billing_first_name" id="billing_first_name" value="<?php if (isset($_POST['billing_first_name']) && empty($_POST['billing_first_name'])) {
        $validation_errors->add('billing_first_name_error', __('Nome  um campo obrigatório!', 'text_domain'));
    } ?>" />
    </p>
    <p class="form-row form-row-last" id="billing_last_name_field">
        <label for="billing_last_name" class="fl-label">Sobrenome&nbsp;<abbr class="required" title="obrigatrio">*</abbr></label>
        <input type="text" class="input-text fl-input" name="billing_last_name" id="billing_last_name" autocomplete="family-name" value="<?php if (isset($_POST['billing_last_name']) && empty($_POST['billing_last_name'])) {
        $validation_errors->add('billing_last_name_error', __('Sobrenome  um campo obrigatório!', 'text_domain'));
    } ?>"/>
    </p>
    
    <div id="pf_fields" style="<?php echo $saved === '2' ? 'display:none;' : ''; ?>">
        <p class="form-row form-row-wide fl-is-required">
            <label for="billing_cpf" class="fl-label">CPF <abbr class="required">*</abbr></label>
            <input type="text" class="input-text form-row required fl-label" name="billing_cpf" id="billing_cpf"
                   data-bmw-mask="cpf"
                   value="<?php echo esc_attr( get_user_meta( get_current_user_id(), 'billing_cpf', true ) ); ?>"/>
            <span id="cpf_error" style="color:red; display:none;"></span>
        </p>
    </div>
    
    <p class="form-row form-row-wide maskedinput maskPhone">
    <label for="billing_phone"><?php _e( 'Phone', 'woocommerce' ); ?></label>
    <input type="tel" maxlength="13" class="input-text fl-input is_phone format-phone format_phone phone_validation is-phone" name="billing_phone" id="billing_phone" value="<?php wc_enqueue_js( "
      $('#billing_phone')
      .keydown(function(e) {
         var key = e.which || e.charCode || e.keyCode || 0;
         var phone = $(this);         
         if (key !== 8 && key !== 9) {
           if (phone.val().length === 2) {
            phone.val(phone.val() + '-'); // add dash after char #2
           }
           if (phone.val().length === 8) {
            phone.val(phone.val() + '-'); // add dash after char #8
           }
         }
         return (key == 8 ||
           key == 9 ||
           key == 46 ||
           (key >= 48 && key <= 57) ||
           (key >= 96 && key <= 105));
        });
         
   " ); if (isset($_POST['billing_phone']) && empty($_POST['billing_phone'])) {
        $validation_errors->add('billing_phone_error', __('Celular é um campo obrigatório!', 'text_domain'));
    } ?>" />
    </p>

    <div class="clear"></div>
    
    <?php
    
    }
}
    add_action( 'woocommerce_register_form_start', 'woo_extra_register_fields' );
    
    function text_domain_woo_validate_reg_form_fields($username, $email, $validation_errors) {
    if (isset($_POST['billing_first_name']) && empty($_POST['billing_first_name'])) {
        $validation_errors->add('billing_first_name_error', __('Nome é um campo obrigatrio!', 'text_domain'));
    }
    if (isset($_POST['billing_last_name']) && empty($_POST['billing_last_name'])) {
        $validation_errors->add('billing_last_name_error', __('Sobrenome  um campo obrigatório!', 'text_domain'));
    }
    if ( empty( $_POST['billing_cpf'] ) ) {
		wc_add_notice( sprintf( '<strong>%s</strong> %s.', __( 'CPF', 'woocommerce-extra-checkout-fields-for-brazil' ), __( 'is a required field', 'woocommerce-extra-checkout-fields-for-brazil' ) ), 'error' );
	}
    if (isset($_POST['billing_phone']) && empty($_POST['billing_phone'])) {
        $validation_errors->add('billing_phone_error', __('Celular  um campo obrigatório!', 'text_domain'));
    }
    
    return $validation_errors;
}
    add_action('woocommerce_register_post', 'text_domain_woo_validate_reg_form_fields', 10, 3);

    function text_domain_woo_save_reg_form_fields($customer_id) {
    //First name field
    if (isset($_POST['billing_first_name'])) {
        update_user_meta($customer_id, 'first_name', sanitize_text_field($_POST['billing_first_name']));
        update_user_meta($customer_id, 'billing_first_name', sanitize_text_field($_POST['billing_first_name']));
    }
    //Last name field
    if (isset($_POST['billing_last_name'])) {
        update_user_meta($customer_id, 'last_name', sanitize_text_field($_POST['billing_last_name']));
        update_user_meta($customer_id, 'billing_last_name', sanitize_text_field($_POST['billing_last_name']));
    }
    //Phone field
    if (isset($_POST['billing_phone'])) {
        update_user_meta($customer_id, 'phone', sanitize_text_field($_POST['billing_phone']));
        update_user_meta($customer_id, 'billing_phone', sanitize_text_field($_POST['billing_phone']));
    }
    }
add_action('woocommerce_created_customer', 'text_domain_woo_save_reg_form_fields');

//CAMPO DE CONFIRMAÇÃO DE EMAIL
add_action( 'init', 'custom_email_confirmation' );
function custom_email_confirmation() {

    /*
     * 1) === REGISTRO: campo de confirmação e validaço AJAX
     */
    add_action( 'woocommerce_register_form', function() {
        if ( is_user_logged_in() ) return;
        $email_confirm_value = isset($_POST['email_confirm']) ? esc_attr($_POST['email_confirm']) : '';
        ?>
        <p class="form-row form-row-wide" id="email_confirm_field">
            <label for="reg_email_confirm">
                Confirmar e-mail&nbsp;<span class="required">*</span>
            </label>
            <input
                type="email"
                class="woocommerce-Input woocommerce-Input--text input-text"
                name="email_confirm"
                id="reg_email_confirm"
                autocomplete="email"
                required
                aria-required="true"
                value="<?php echo $email_confirm_value; ?>"
            >
            <span class="woocommerce-error" id="email_confirm_error" style="display:none; color: red;"></span>
        </p>
        <script type="text/javascript">
        jQuery(function($){
            $('#email_confirm_field').insertAfter($('#reg_email').closest('.form-row'));
            $('#reg_email_confirm').on('blur', function(){
                var email = $('#reg_email').val();
                var confirm = $(this).val();
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'validate_email_confirm',
                    email: email,
                    email_confirm: confirm
                }, function(response){
                    if ( response.success ) {
                        $('#email_confirm_error').hide().text('');
                    } else {
                        $('#email_confirm_error').show().text(response.data);
                    }
                });
            });
        });
        </script>
        <?php
    });

    /*
     * 2) === CHECKOUT: adiciona campo de confirmaço
     */
    add_filter( 'woocommerce_checkout_fields', function( $fields ) {
        if ( !is_user_logged_in() ) {
            $fields['billing']['billing_email_confirm'] = [
                'label'       => 'Confirmar e-mail',
                'required'    => true,
                'type'        => 'email',
                'priority'    => 1,
                'class'       => ['form-row-wide'],
            ];
        }
        return $fields;
    });

    /*
     * 3) === CHECKOUT: validação backend
     */
    add_action( 'woocommerce_checkout_process', function() {
        if ( !is_user_logged_in() ) {
            $email   = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';
            $confirm = isset($_POST['billing_email_confirm']) ? sanitize_email($_POST['billing_email_confirm']) : '';
            if ( $email !== $confirm ) {
                wc_add_notice( 'Os e-mails no coincidem', 'error' );
            }
        }
    });

    // 4) === CHECKOUT: validação AJAX no campo (inline, abaixo do input)
    add_action( 'woocommerce_after_checkout_form', function() {
    if ( is_user_logged_in() ) return;
    ?>
    <script type="text/javascript">
    jQuery(function($){
        $('#billing_email_confirm').on('blur', function(){
            var email   = $('#billing_email').val();
            var confirm = $(this).val();

            // Garante que exista o span de erro logo abaixo do campo
            var $error = $('#billing_email_confirm_error');
            if ( $error.length === 0 ) {
                $error = $('<span class="woocommerce-error" id="billing_email_confirm_error" style="display:none; color:red; margin-top:5px; font-size:0.9em;"></span>');
                $error.insertAfter( $('#billing_email_confirm') );
            }

            $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                action:        'validate_email_confirm',
                email:         email,
                email_confirm: confirm
            }, function(response){
                if ( response.success ) {
                    $error.hide().text('');
                } else {
                    $error.show().text(response.data);
                }
            });
        });
    });
    </script>
    <?php
    });



    /*
     * 5) === BACKEND AJAX handler compartilhado (registro + checkout)
     */
    add_action( 'wp_ajax_nopriv_validate_email_confirm', function() {
        $email   = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $confirm = isset($_POST['email_confirm']) ? sanitize_email(wp_unslash($_POST['email_confirm'])) : '';
        if ( $email !== $confirm ) {
            wp_send_json_error('Os e-mails no coincidem');
        }
        wp_send_json_success();
    });

    /*
     * 6) === REGISTRO: validao backend
     */
    add_action( 'woocommerce_register_post', function($username, $email, $validation_errors) {
        if ( isset($_POST['email'], $_POST['email_confirm']) ) {
            $email = sanitize_email($_POST['email']);
            $confirm = sanitize_email($_POST['email_confirm']);
            if ( $email !== $confirm ) {
                $validation_errors->add('email_confirmation_error', 'Os e-mails não coincidem');
            }
        }
    }, 10, 3 );
}

/*
 * 7) === Capitalizaão e lowercase (mantido)
 */
add_action('wp_footer', function() {
    if ( is_checkout() || is_account_page() ) { ?>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const inputs = document.querySelectorAll('.woocommerce input[type="text"], .woocommerce input[type="email"]');

            inputs.forEach(input => {
                if (input.type === 'email' || input.id.includes('email') || input.id === 'username' || input.id === 'coupon_code') {
                    input.addEventListener('input', function () {
                        input.value = input.value.toLowerCase();
                    });
                } else if (input.type !== 'password') {
                    input.addEventListener('input', function () {
                        let value = input.value.toLowerCase();
                        input.value = value.replace(/(^|\s)([a-záàâãäéèêëíìôüç])/g, function(match) {
                            return match.toUpperCase();
                        });
                    });
                }
            });
        });
        </script>
    <?php }
});

