<?php defined('SYSPATH') or die('No direct script access.');

class Model_Product extends ORM {

    protected $_table_name = 'products';
    protected $_primary_key = 'id';
    protected $_db_group = 'default';
    protected $pagination;

    /**
     * Auto-update columns for updates
     * @var string
     */
    protected $_updated_column = array('column' => 'updated', 'format' => 'Y-m-d H:i:s');

    /**
     * Auto-update columns for creation
     * @var string
     */
    protected $_created_column = array('column' => 'created', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'images' => array(
            'model' => 'Image',
            'foreign_key' => 'product_id',
        ),
        'categories' => array(
            'model' => 'category',
            'foreign_key' => 'product_id',
            'through' => 'products_categories',
            'far_key' => 'category_id',
        ),
        'male_types' => array(
            'model' => 'Male',
            'foreign_key' => 'product_id',
            'through' => 'products_male_types',
            'far_key' => 'male_id',
        ),

        'product_types' => array(
            'model' => 'Prodtype',
            'foreign_key' => 'product_id',
            'through' => 'product_types_products',
            'far_key' => 'prodtype_id',
        ),
    );

    protected $_belongs_to = array(
        'main_img' => array(
            'model' => 'Image',
            'foreign_key' => 'image',
        ),
    );

    public function rules(){
        return array(
            'title' => array(
                array('not_empty'),
            ),

            'alias' => array(
                array('not_empty'),
                array('alpha_dash'),
                array(array($this, 'unique'), array('alias', ':value')),
            ),

            'meta_title' => array(
                array('max_length', array(':value', 800)),
            ),

            'meta_desc' => array(
                array('max_length', array(':value', 800)),
            ),

            'meta_keys' => array(
                array('max_length', array(':value', 800)),
            ),
            'ingredients' => array(
                array('max_length', array(':value', 800)),
            ),
            'cost' => array(
                array('not_empty'),
                array('numeric'),
            ),
            'weight' => array(
                array('not_empty'),
                array('numeric'),
            ),
        );
    }


    public function filters()
    {
        return array(
            TRUE => array(
                array('trim'),
            ),
        );
    }

    public function get_for_search(){
        $query = DB::select('products.title','products.desc','products.alias', 'products.meta_title','products.meta_keys', 'products.cost','products.weight', 'images.path')
            ->from('products')
            ->join('images')
            ->on('products.image', '=', 'images.id')
            ->execute()
            ->as_array();
        return $query;
    }

    public function novelties($limit = false, $offset = false, $category = null, $prod_type = null, $male_type = null){

        $query = DB::select(
            $this->_table_name.'.id',
            $this->_table_name.'.image',
            $this->_table_name.'.title',
            $this->_table_name.'.alias',
            $this->_table_name.'.cost',
            $this->_table_name.'.weight',
            'images.path'
        )
            ->from($this->_table_name)
            ->join('images')
            ->on('products.image', '=', 'images.id');

        if($limit !== false){
            $query = $query->limit((int)$limit);
        }
        if($offset !== false){
            $query = $query->offset((int)$offset);
        }

        if(!empty($category)){
            $query = $query->join('products_categories','INNER')
                ->on('products_categories.product_id','=','products.id')
                ->join('categories','INNER')
                ->on('products_categories.category_id','=','categories.id')
                ->and_where('categories.alias','=',':category')
                ->bind(':category',$category);
        }

        if(!empty($prod_type)){
            $query = $query->join('product_types_products','INNER')
                ->on('product_types_products.product_id','=','products.id')
                ->join('product_types','INNER')
                ->on('product_types_products.prodtype_id','=','product_types.id')
                ->and_where('product_types.alias','=',':type')
                ->bind(':type',$prod_type);
        }

        if(!empty($male_type)){
            $query = $query->join('products_male_types','INNER')
                ->on('products_male_types.product_id','=','products.id')
                ->join('male_types','INNER')
                ->on('products_male_types.male_id','=','male_types.id')
                ->and_where('male_types.alias','=',':maletype')
                ->bind(':maletype',$male_type);
        }

        $result = $query->order_by($this->_table_name.'.created','DESC')->execute()->as_array();
        return $result;
    }
}