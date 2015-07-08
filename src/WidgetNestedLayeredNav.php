<?php

class WidgetNestedLayeredNav extends WC_Widget_Layered_Nav
{
    public $taxonomy;
    public $current_term;
    public $query_type;
    public $instance;
    public $found;

    /**
     * Init settings after post types are registered
     *
     * @return void
     */
    public function init_settings()
    {
        $attribute_array      = array();
        $attribute_taxonomies = wc_get_attribute_taxonomies();

        if ($attribute_taxonomies) {
            foreach ($attribute_taxonomies as $tax) {
                if (taxonomy_exists(wc_attribute_taxonomy_name($tax->attribute_name))) {
                    $attribute_array[ $tax->attribute_name ] = $tax->attribute_name;
                }
            }
        }

        $this->settings = array(
            'title' => array(
                'type'  => 'text',
                'std'   => __('Filter by', 'woocommerce'),
                'label' => __('Title', 'woocommerce')
            ),
            'attribute' => array(
                'type'    => 'select',
                'std'     => '',
                'label'   => __('Attribute', 'woocommerce'),
                'options' => $attribute_array
            ),
            'query_type' => array(
                'type'    => 'select',
                'std'     => 'and',
                'label'   => __('Query type', 'woocommerce'),
                'options' => array(
                    'and' => __('AND', 'woocommerce'),
                    'or'  => __('OR', 'woocommerce')
                )
            ),
        );
    }

    /**
     * widget function.
     *
     * @see WP_Widget
     *
     * @param array $args
     * @param array $instance
     *
     * @return void
     */
    public function widget($args, $instance)
    {
        global $_chosen_attributes;

        $this->instance = $instance;

        if (! is_post_type_archive('product') && ! is_tax(get_object_taxonomies('product'))) {
            return;
        }

        $this->current_term = is_tax() ? get_queried_object()->term_id : '';
        $current_tax  = is_tax() ? get_queried_object()->taxonomy : '';
        $this->taxonomy = isset($instance['attribute']) ? wc_attribute_taxonomy_name($instance['attribute']) : $this->settings['attribute']['std'];
        $this->query_type = isset($instance['query_type']) ? $instance['query_type'] : $this->settings['query_type']['std'];
        $display_type = isset($instance['display_type']) ? $instance['display_type'] : $this->settings['display_type']['std'];

        if (! taxonomy_exists($this->taxonomy)) {
            return;
        }

        $get_terms_args = array( 'hide_empty' => '1' );

        $orderby = wc_attribute_orderby($this->taxonomy);

        switch ($orderby) {
            case 'name' :
                $get_terms_args['orderby']    = 'name';
                $get_terms_args['menu_order'] = false;
            break;
            case 'id' :
                $get_terms_args['orderby']    = 'id';
                $get_terms_args['order']      = 'ASC';
                $get_terms_args['menu_order'] = false;
            break;
            case 'menu_order' :
                $get_terms_args['menu_order'] = 'ASC';
            break;
        }

        $terms = get_terms($this->taxonomy, $get_terms_args);

        if (0 < count($terms)) {
            ob_start();

            $this->found = false;

            $this->widget_start($args, $instance);

            // Force found when option is selected - do not force found on taxonomy attributes
            if (! is_tax() && is_array($_chosen_attributes) && array_key_exists($this->taxonomy, $_chosen_attributes)) {
                $this->found = true;
            }

            $filters = array();

            foreach ($terms as $term) {
                if ($term->parent == '0') {
                    $filters[$term->term_id]['parent'] = $this->build_list_item($term);
                } elseif ($term->parent != '0') {
                    $filters[$term->parent]['children'][] = $this->build_list_item($term);
                }
            }

            // List display
            echo '<ul>';

            foreach ($filters as $filter) {
                echo $filter['parent'];

                if (! empty($filter['children'])) {
                    echo '<ul>';
                    foreach ($filter['children'] as $child) {
                        echo $child . '</li>';
                    }
                    echo '</ul>';
                }

                echo '</li>'; // close list
            }

            echo '</ul>';

            $this->widget_end($args);
            if (! $this->found) {
                ob_end_clean();
            } else {
                echo ob_get_clean();
            }
        }
    }

    public function build_list_item($term)
    {
        global $_chosen_attributes;

        // Get count based on current view - uses transients
        $transient_name = 'wc_ln_count_' . md5(sanitize_key($this->taxonomy) . sanitize_key($term->term_taxonomy_id));

        if (false === ($_products_in_term = get_transient($transient_name))) {
            $_products_in_term = get_objects_in_term($term->term_id, $this->taxonomy);

            set_transient($transient_name, $_products_in_term);
        }

        $option_is_set = (isset($_chosen_attributes[ $this->taxonomy ]) && in_array($term->term_id, $_chosen_attributes[ $this->taxonomy ]['terms']));

        // skip the term for the current archive
        if ($this->current_term == $term->term_id) {
            continue;
        }

        // If this is an AND query, only show options with count > 0
        if ('and' == $this->query_type) {
            $count = sizeof(array_intersect($_products_in_term, WC()->query->filtered_product_ids));

            if (0 < $count && $this->current_term !== $term->term_id) {
                $this->found = true;
            }

        // If this is an OR query, show all options so search can be expanded
        } else {
            $count = sizeof(array_intersect($_products_in_term, WC()->query->unfiltered_product_ids));

            if (0 < $count) {
                $this->found = true;
            }
        }

        $arg = 'filter_' . sanitize_title($this->instance['attribute']);

        $current_filter = (isset($_GET[ $arg ])) ? explode(',', $_GET[ $arg ]) : array();

        if (! is_array($current_filter)) {
            $current_filter = array();
        }

        $current_filter = array_map('esc_attr', $current_filter);

        if (! in_array($term->term_id, $current_filter)) {
            $current_filter[] = $term->term_id;
        }

        // Base Link decided by current page
        if (defined('SHOP_IS_ON_FRONT')) {
            $link = home_url();
        } elseif (is_post_type_archive('product') || is_page(wc_get_page_id('shop'))) {
            $link = get_post_type_archive_link('product');
        } else {
            $link = get_term_link(get_query_var('term'), get_query_var('taxonomy'));
        }

        // All current filters
        if ($_chosen_attributes) {
            foreach ($_chosen_attributes as $name => $data) {
                if ($name !== $this->taxonomy) {

                    // Exclude query arg for current term archive term
                    while (in_array($this->current_term, $data['terms'])) {
                        $key = array_search($this->current_term, $data);
                        unset($data['terms'][$key]);
                    }

                    // Remove pa_ and sanitize
                    $filter_name = sanitize_title(str_replace('pa_', '', $name));

                    if (! empty($data['terms'])) {
                        $link = add_query_arg('filter_' . $filter_name, implode(',', $data['terms']), $link);
                    }

                    if ('or' == $data['query_type']) {
                        $link = add_query_arg('query_type_' . $filter_name, 'or', $link);
                    }
                }
            }
        }

        // Min/Max
        if (isset($_GET['min_price'])) {
            $link = add_query_arg('min_price', $_GET['min_price'], $link);
        }

        if (isset($_GET['max_price'])) {
            $link = add_query_arg('max_price', $_GET['max_price'], $link);
        }

        // Orderby
        if (isset($_GET['orderby'])) {
            $link = add_query_arg('orderby', $_GET['orderby'], $link);
        }

        // Current Filter = this widget
        if (isset($_chosen_attributes[ $this->taxonomy ]) && is_array($_chosen_attributes[ $this->taxonomy ]['terms']) && in_array($term->term_id, $_chosen_attributes[ $this->taxonomy ]['terms'])) {
            $class = ' class="chosen"';

            // Remove this term is $current_filter has more than 1 term filtered
            if (sizeof($current_filter) > 1) {
                $current_filter_without_this = array_diff($current_filter, array( $term->term_id ));
                $link = add_query_arg($arg, implode(',', $current_filter_without_this), $link);
            }
        } else {
            $class = '';
            $link = add_query_arg($arg, implode(',', $current_filter), $link);
        }

        // Search Arg
        if (get_search_query()) {
            $link = add_query_arg('s', get_search_query(), $link);
        }

        // Post Type Arg
        if (isset($_GET['post_type'])) {
            $link = add_query_arg('post_type', $_GET['post_type'], $link);
        }

        // Query type Arg
        if ($this->query_type  == 'or' && ! (sizeof($current_filter) == 1 && isset($_chosen_attributes[ $this->taxonomy ]['terms']) && is_array($_chosen_attributes[ $this->taxonomy ]['terms']) && in_array($term->term_id, $_chosen_attributes[ $this->taxonomy ]['terms']))) {
            $link = add_query_arg('query_type_' . sanitize_title($this->instance['attribute']), 'or', $link);
        }

        $r = '';
        $r .= '<li' . $class . '>';

        $r .= ($count > 0 || $option_is_set) ? '<a href="' . esc_url(apply_filters('woocommerce_layered_nav_link', $link)) . '">' : '<span>';

        $r .= $term->name;

        $r .= ($count > 0 || $option_is_set) ? '</a>' : '</span>';

        $r .= ' <span class="count">(' . $count . ')</span>';

        return $r; // without closing </li>, because we might nest an <ul> later
    }
}
