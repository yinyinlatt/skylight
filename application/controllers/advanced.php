<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once('skylight.php');

class Advanced extends skylight {

    function Advanced() {
        // Initalise the parent
        parent::__construct();
    }

    function _remap($path, $params = array()) {

            $form = form_open('advanced/post');
            $fields = $this->config->item('skylight_search_fields');

            foreach($fields as $key => $value) {
                  $escaped_key = $this->_escape($key);
                  $input_data = array(
                      'name'        => $escaped_key,
                      'id'          => $escaped_key,
                      'value'       => '',
                      'maxlength'   => '50',
                      'size'        => '50',
                      'style'       => 'margin-left: 15px;'
                    );

                $form .= '<p>';

                $form .= form_label($key, $escaped_key, array('style' => 'width: 100px; float: left; display: block; text-align: right;'));
                $form .= form_input($input_data);

                $form .= '</p>';
            }
            $form .= '<p>'.form_label('Default search operator', 'operators', array('style' => 'width: 100px; float: left; display: block; text-align: right;'));
            $operators = array('AND' => 'AND (all terms must match)', 'OR' => 'OR (any terms may match)');
            $form .= form_dropdown('operator',$operators,'AND','style="margin-left:15px;"').'</p>';
            $form .= '<p style="margin-left: 120px;"><em>Use <strong>AND</strong> for narrow searches and <strong>OR</strong> for broad searches</em></p>';
            $form .= form_submit('search', 'Search', 'style="margin-left: 120px"');
            $form .= '</form>';


        if ((empty($path)) || ($path == 'index')) {
            redirect('./advanced/form');
        }
        else if($path == 'form') {


            $formdata['form'] = $form;
            $formdata['formhidden'] = false;

            // Set the page title to the record title
            $data['page_title'] = 'Advanced Search';
            $this->view('header', $data);
            $this->view('div_main');
            $this->view('advanced_search',$formdata);
            $this->view('div_main_end');
            $this->view('div_sidebar');
            $this->view('search_box');
            $this->view('div_sidebar_end');
            $this->view('footer');
        }
        else if($path == 'post') {

            // We can't use * in URL path, so we might need to rethink that one..
            $query = '*:*';
            $filters = '';
            $filterurl = '';

            $delimiter = $this->config->item('skylight_filter_delimiter');
            $recorddisplay = $this->config->item('skylight_recorddisplay');
            $rows = $this->config->item('skylight_results_per_page');
            $title = $recorddisplay['Title'];

            $search_fields = $this->config->item('skylight_search_fields');
            foreach($search_fields as $label => $field) {
                $val = $this->input->post($this->_escape($label));
                if(isset($val) && $val != '') {
                    $filters .= '&fq='.$field.':'.$val.'';
                    $filterurl .= '/'.$label.':'.$val;
                }
            }
            $operator = $this->input->post('operator');
            // Base search URL
            redirect($base_search = './advanced/search'.$filterurl.'?operator='.$operator);

        }
        else if($path == 'search') {

           $query = '';
            $operator = $this->input->get('operator');
            $offset = $this->input->get('offset');
            $configured_filters = $this->config->item('skylight_filters');
        $delimiter = $this->config->item('skylight_filter_delimiter');
        $rows = $this->config->item('skylight_results_per_page');
        $recorddisplay = $this->config->item('skylight_recorddisplay');
        $display_thumbnail = $this->config->item('skylight_display_thumbnail');
        $thumbnail_field = $this->config->item('skylight_thumbnail_field');
        $title = $recorddisplay['Title'];
        $search_fields = $this->config->item('skylight_search_fields');
        $saved_filters = array();
        $saved_search = array();
        $url_filters = array();
        $message = '<h3>Currently searching the following fields: </h3>';
        $filter_message = '';
        if(count($this->uri->segments) > 2) {

            for($i = 3; $i <= count($this->uri->segments); $i++) {
                $test_filter = $this->uri->segments[$i];
                if(preg_match('#%7C%7C%7C#',$test_filter)) {
                    $url_filters[] = $test_filter;
                    $filter_segments = preg_split("/$delimiter/",$test_filter, 2);
                    if(array_key_exists($filter_segments[0], $configured_filters)) {
                        $saved_filters[] = $configured_filters[$filter_segments[0]].$delimiter.$filter_segments[1];
                        $display_value = preg_split("#%7C%7C%7C#",$filter_segments[1],2);
                        $filter_message .= '<strong>'.$filter_segments[0].'</strong> matches "'.urldecode($display_value[1]).'<br/>';

                    }
                }
                else {
                    $url_filters[] = $test_filter;
                    $test_filter = urldecode($test_filter);
                    $filter_segments = preg_split("/$delimiter/",$test_filter, 2);
                    if(array_key_exists($filter_segments[0], $search_fields)) {
                        $saved_filters[] = $search_fields[$filter_segments[0]].$delimiter.$filter_segments[1];
                        $saved_search[$filter_segments[0]] = $filter_segments[1];
                        $message .= '<strong>'.$filter_segments[0].'</strong> : '.urldecode($filter_segments[1]).'<br/>';
                    }
                }
            }

            if($filter_message != '') {
                $message .= '<h3>Currently applying the following search filters: </h3>' . $filter_message;
            }

        }



        // Base search URL
        $base_search = './advanced/search';
        foreach($url_filters as $url_filter) {
            $base_search .= '/'.$url_filter;
        }

        // Solr query business moved to solr_client library
        $data = $this->solr_client->simpleSearch($query, $offset, $saved_filters, $operator);

        // Inject query back into results
        $data['query'] = $query;
        $data['base_search'] = $base_search;
        $data['delimiter'] = $delimiter;
        $data['saved_search'] = $saved_search;
        $data['operator'] = $operator;

        // Variables to populate the search box
        $data['searchbox_query'] = $query;
        if (($data['searchbox_query'] == '*') || ($data['searchbox_query'] == '*:*')) $data['searchbox_query'] = '';
        $data['searchbox_filters'] = $saved_filters;

        $data['form'] = $form;
        $data['formhidden'] = true;

        $data['message'] = $message;

        // Check for zero results
        $result_count = $data['rows'];
        if ($result_count == 0) {
            $data['page_title'] = 'No search results found!';
            $this->view('header', $data);
            $this->view('div_main');
            $this->view('advanced_search',$data);
            $this->view('search_noresults');
            $this->view('div_main_end');
            $this->view('div_sidebar');
            $this->view('search_box');
            $this->view('search_facets',$data);
            $this->view('div_sidebar_end');
            $this->view('footer');
            return;
        }

        // Load and initialise pagination
        $this->load->library('pagination');
        $config['page_query_string'] = TRUE;
        $config['num_links'] = 2;
        $config['total_rows'] = $result_count;
        $config['per_page'] = $rows;
        $config['base_url'] = $base_search.'?operator='.$operator;
        $this->pagination->initialize($config);


        $data['pagelinks'] = $this->pagination->create_links();

        $data['startrow'] = $offset + 1;
        if($data['startrow'] + ($rows - 1 )  > $result_count)
            $data['endrow'] = $result_count;
        else
            $data['endrow'] = $data['startrow'] + ($rows - 1);

        // Set the page title to the record title
        $data['page_title'] = 'Search results for "<span class=searched>'.urldecode($query).'</span>"';
        $data['title_field'] = $title;
        $data['author_field'] = $recorddisplay['Author'];
        $data['artist_field'] = array_key_exists('Artist',$recorddisplay) ? $recorddisplay['Artist'] : 'dccontributorillustratoren';

        $data['display_thumbnail'] = $display_thumbnail;
        $data['thumbnail_field'] = $thumbnail_field;

        $data['form'] = $form;
        $data['formhidden'] = true;

        $this->view('header', $data);
        $this->view('div_main');
        $this->view('advanced_search', $data);
        $this->view('search_results', $data);
        $this->view('div_main_end');
        $this->view('div_sidebar');
        $this->view('search_box');
        $this->view('search_facets', $data);

        $this->view('div_sidebar_end');
        $this->view('footer');
        }
    }
}