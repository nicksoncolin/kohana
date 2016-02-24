<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Index_Catalog extends Controller_Index {
    protected $_item;
    protected $left_menu;

    public function before(){
        parent::before();
        $cat_alias = Request::initial()->param('catalias');
        $categories = ORM::factory('Category')->order_by('ordering', 'ASC')->where('hide', '=', 0)->find_all();

        $this->left_menu = View::factory('index/left_menu', array(
            'categories'   => $categories,
            'cat_alias' =>  $cat_alias,
        ));
    }


    public function action_list(){

        $cat_alias = Request::initial()->param('catalias');
        $page = Request::initial()->param('page');
        $type = Request::initial()->param('type');
        $for = Request::initial()->param('male');
        $lang = I18n::$lang !== $this->_primary_lang->iso ? I18n::$lang : null;

        $count = count(Model::factory('Product')->novelties($limit = false, $offset = false,$cat_alias , $type, $for));
        $pagination = Pagination::factory(array(
            'total_items' => $count,
            'items_per_page'    => 9,
            'view'        => 'index/pagination/index_pagination',
        ))->route_params(array('action'=> 'list','catalias' => $cat_alias, 'type' => $type?$type:null,'male' => $for?$for:null)) ;

       //stanum enq cateorian sesiayi mijocov
        if($cat_alias){
            $this->_session->set('category', $cat_alias);
        }
        // stanum enq categoryaner#

        //stanum 1 category
        if($cat_alias){
            $this->_item = ORM::factory('Category',array('alias' => $cat_alias));
            if(!$this->_item->loaded()){
                throw new HTTP_Exception_404();
            }
        }

        //stanum enq filtreri  URL
        if (isset($_POST['type']) OR isset($_POST['for'])){

            $type = $_POST['type'] ? $_POST['type'] : null;
            $for = $_POST['for'] ? $_POST['for'] : null;

            $filtr_url = Model::factory('Filter')->url($lang, $cat_alias, $type, $for);

            $this->redirect($filtr_url);
        }

        if($type OR $for){
            $filters = Model::factory('Filter')->check($type, $for);
            if(!$filters){
                throw new HTTP_Exception_404();
            }
        }

        $products = Model::factory('Product')->novelties($pagination->items_per_page, $pagination->offset, $cat_alias , $type, $for);

        $content = View::factory('index/catalog/content',array(
            'cat_alias' =>  $cat_alias,
            'left_menu' =>  $this->left_menu,
            'products'  =>  $products,
            'pagination'    =>  $pagination,
            'page'  =>  $page,
            'product_types' =>  Model::factory('Prodtype')->types($cat_alias),
            'male_types'    =>  Model::factory('Male')->male_types($cat_alias),
            'category'      =>  $this->_item,
            'type'         =>  $type,
            'for'       =>  $for

        ));

        $this->template->content = $content;
    }



    public function action_anketa(){

        $prod_alias = Request::initial()->param('prodalias');

        //stanum enq 1 hat product
        $this->_item = ORM::factory('Product', array('alias' => $prod_alias));
        if(!$this->_item->loaded()){
            throw new HTTP_Exception_404();
        }

        // stanum en categoryan aliasi mijocov
        $data = $this->_session->as_array();
        if(!empty($data['category'])){
            $cat = $data['category'];
            $category_s = ORM::factory('Category', array('alias' => $cat));
            $this->_session->delete('category');
        }else{
            $category_s = $this->_item->categories->find();
        }

        //stanum enq nmanatip apranqner@
        $prod = array();
        $category = $this->_item->categories->where('hide', '=', 0)->find_all();
        foreach($category as $items){
            $categories = ORM::factory('Category', array('alias' => $items->alias));
            foreach($categories->products->order_by('created','DESC')->order_by('updated','DESC')->find_all() as $item){
                if($this->_item->alias !== $item->alias){
                    $prod[] = $item;
                }
            }
        }
        $products  = array_splice($prod, 0, 4);

        $content = View::factory('index/catalog/anketa', array(
            'product'  =>  $this->_item,
            'images' => $this->_item->images->find_all(),
            'products' =>  $products,
            'category_s'    =>  $category_s,
        ));
        $this->template->content = $content;
    }

    protected $items;
    protected $total_items;

    public function action_search(){

        $page = Request::initial()->param('page');
        
        
        
        $lang = I18n::$lang !== $this->_primary_lang->iso ? I18n::$lang : null;

        $search_text = trim(UTF8::str_ireplace('_', ' ', $this->request->param('search_text')));

        if(preg_match('~([,\'"#&<>$:;])\\/~', $search_text)) throw new HTTP_Exception_404();
        if(isset($_POST['submit']))
        {
            $search_text = Arr::get($_POST,'searchwords');

            if (!empty($search_text))
            {
                $search_text =UTF8::str_ireplace(' ', '_', HTML::chars(UTF8::clean($search_text)));
                $search_uri = preg_replace('~([,\'"#&<>$:;])\\/~','', $search_text);
                $this->redirect('/'.$lang.'/search/'.$search_uri);
            }
        }

        $this->items = Model::factory('Product')->get_for_search();
        $search = new Search($search_text,$this->items,
            array(
                'title' => 0.2,
                'meta_title' => 0.1,
                'meta_keys' => 0.1,
                'desc' => 0.1,
                'weight' => 0.5,
                'cost' => 0.6,
            )
            ,0);

        $this->total_items = $search->get_items_count();

        $this->item['title'] = $search->get_result_text();

        $this->_item = $search_text;

        $pagination = Pagination::factory(array(
            'total_items' => $this->total_items,
            'items_per_page'    => $search->set_limit(9),
            'offset'    =>  $search->set_offset($page-1),
            'view'        => 'index/pagination/index_pagination',
        ))->route_params(array('action'=> 'list','search_text' => $search_text));


        //var_dump($page); die;
        
        $this->template->content = View::factory('index/catalog/search', array(
            'left_menu' =>  $this->left_menu,
            'title_search'  =>  $this->item['title'],
            'page'  =>  $page,
            'result'    =>  $search->get_result(),
            'pagination'    =>  $pagination,
        ));
    }

    public function after(){

        $type = Request::initial()->param('type');
        $for = Request::initial()->param('male');
        $product_type = ORM::factory('Prodtype', array('alias' => $type));
        $male_type = ORM::factory('Male', array('alias' => $for));
        $product_type_title = Translate::text($product_type->title, I18n::$lang) ?  "-".Translate::text($product_type->title, I18n::$lang) : null;
        $male_type_title = Translate::text($male_type->title, I18n::$lang)  ? "-".Translate::text($male_type->title, I18n::$lang) : null;


        if ($this->_item instanceof ORM){
            $title =!empty($this->_item->meta_title) ? Translate::text($this->_item->meta_title, I18n::$lang) : Translate::text($this->_item->title, I18n::$lang);
            if($type OR $for){
                $this->template->meta_title = $title.$product_type_title.$male_type_title;
            }
            else{
                $this->template->meta_title = $title;
                $this->template->meta_description = Translate::text($this->_item->meta_desc, I18n::$lang);
                $this->template->meta_keys = Translate::text($this->_item->meta_keys, I18n::$lang);
            }
        }else{
            $this->template->meta_title = $this->_item ? $this->_item : __('products').$product_type_title.$male_type_title;
        }
        parent::after();
    }
}