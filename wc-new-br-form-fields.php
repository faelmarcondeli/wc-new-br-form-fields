<?php
/**
 * Plugin Name: Frete Grátis por Classe
 * Description: Permite oferecer frete grátis baseado em classe de entrega e valor mínimo, aplicando apenas a regra com maior valor mínimo atingido.
 * Version: 1.0
 * Author: Rafael Moreno
 */

if (!defined('ABSPATH')) exit;


// FREE SHIPPING_LABEL
add_filter( 'woocommerce_cart_shipping_method_full_label', 'bbloomer_add_0_to_shipping_label', 10, 2 );
function bbloomer_add_0_to_shipping_label( $label, $method ) {
    
    if ((is_account_page() || is_checkout() || is_cart()) && is_admin() && !defined('DOING_AJAX')) return;
    
    // if shipping rate is 0, concatenate ": $0.00" to the label
    if ( ! ( $method->cost > 0 ) ) {
    $label .= ': <strong><span style="color: #50b848;">GRÁTIS</span></strong>';
    }
    return $label;
}


// Adiciona um campo de classe de entrega aos métodos de frete grátis
add_filter( 'woocommerce_shipping_instance_form_fields_free_shipping', 'adicionar_classe_entrega_frete_gratis' );

function adicionar_classe_entrega_frete_gratis( $fields ) {
    $shipping_classes = WC()->shipping()->get_shipping_classes();
    $options = ['' => 'Todas as classes'];

    foreach ( $shipping_classes as $class ) {
        $options[ $class->slug ] = $class->name;
    }

    $fields['frete_gratis_para_classe'] = [
        'title'       => 'Classe de Entrega',
        'type'        => 'select',
        'description' => 'Opcional. Limita este frete grátis a uma classe de entrega específica.',
        'default'     => '',
        'desc_tip'    => true,
        'options'     => $options,
    ];

    return $fields;
}

add_filter( 'woocommerce_package_rates', 'filtrar_metodos_por_classe_prioritaria', 20, 2 );

function filtrar_metodos_por_classe_prioritaria( $rates, $package ) {
    $prioridade_classes = ['volumetric-weight', 'brasil'];
    $classes_no_carrinho = [];

    foreach ( $package['contents'] as $item ) {
        $classe = $item['data']->get_shipping_class();
        if ( $classe && ! in_array( $classe, $classes_no_carrinho ) ) {
            $classes_no_carrinho[] = $classe;
        }
    }

    if ( empty( $classes_no_carrinho ) ) return $rates;

    // Determina a classe prioritária no carrinho
    $classe_prioritaria = null;
    foreach ( $prioridade_classes as $classe ) {
        if ( in_array( $classe, $classes_no_carrinho ) ) {
            $classe_prioritaria = $classe;
            break;
        }
    }

    if ( ! $classe_prioritaria ) return $rates;

    // Encontrar a zona de entrega correspondente ao pacote (com base em postcode, etc.)
    $zone = WC_Shipping_Zones::get_zone_matching_package( $package );
    $zone_methods = $zone->get_shipping_methods();
    $metodos_por_id = [];

    foreach ( $zone_methods as $method ) {
        $metodos_por_id[ $method->instance_id ] = $method;
    }

    $rates_filtradas = [];

    foreach ( $rates as $rate_id => $rate ) {
        $instance_id = $rate->instance_id;

        // Verificamos apenas os métodos que realmente pertencem à zona ativa
        if ( ! isset( $metodos_por_id[ $instance_id ] ) ) {
            continue; // pula métodos que não estão na zona válida
        }

        $metodo = $metodos_por_id[ $instance_id ];

        if ( $rate->method_id === 'free_shipping' ) {
            $classe_configurada = $metodo->get_option( 'frete_gratis_para_classe' );

            if ( empty( $classe_configurada ) || $classe_configurada === $classe_prioritaria ) {
                $rates_filtradas[ $rate_id ] = $rate;
            }

        } else {
            // Mantém se for da classe prioritária ou se for método genérico (sem classe definida)
            if (
                strpos( $rate_id, $classe_prioritaria ) !== false ||
                strpos( strtolower( $rate->label ), $classe_prioritaria ) !== false
            ) {
                $rates_filtradas[ $rate_id ] = $rate;
            } else {
                // Mantém também se o método não estiver amarrado a nenhuma classe específica
                // (isso vai depender da sua regra de nomeação ou configuração do método)
                $rates_filtradas[ $rate_id ] = $rate;
            }
        }
    }

    return !empty( $rates_filtradas ) ? $rates_filtradas : $rates;
}

// ESPUMAS SOB MEDIDA MODIFICATION
add_action('woocommerce_check_cart_items', 'limitar_espumas_para_frete_gratis');
function limitar_espumas_para_frete_gratis() {
    
    if ((is_account_page() || is_checkout() || is_cart()) && is_admin() && !defined('DOING_AJAX')) return;

    $valor_maximo = 600; // Limite em reais
    $categoria_alvo = 'volumetric-weight';
    $total_espumas = 0;

    // Verifica se o frete grtis foi selecionado
    $chosen_methods = WC()->session->get('chosen_shipping_methods');
    if (empty($chosen_methods)) return;

    $frete_gratis_ativo = false;
    foreach ($chosen_methods as $method) {
        if (strpos($method, 'free_shipping') !== false) {
            $frete_gratis_ativo = true;
            break;
        }
    }

    if (!$frete_gratis_ativo) return;

    // Soma os valores dos produtos da categoria "volumetric-weight"
    foreach (WC()->cart->get_cart() as $item) {
        $product = $item['data'];

        if (has_term($categoria_alvo, 'product_cat', $product->get_id())) {
            $total_espumas += $item['line_total'];
        }
    }

    if ($total_espumas > $valor_maximo) {
        wc_add_notice(
            sprintf(
                'Para utilizar o frete grátis, o valor dos produtos da categoria "Espumas e Enchimentos" não pode ultrapassar R$ %s. Atualmente : R$ %s.',
                number_format($valor_maximo, 2, ',', '.'),
                number_format($total_espumas, 2, ',', '.')
            ),
            'error'
        );
    }
}
