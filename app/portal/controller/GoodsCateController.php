<?php
namespace app\portal\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use app\portal\model\GoodsCateModel;
use think\Db;
use app\admin\model\ThemeModel;


class GoodsCateController extends AdminBaseController
{
    /**
     * 品类分类列表
     * @adminMenu(
     *     'name'   => '品类管理',
     *     'parent' => 'portal/GoodsCate/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '品类分类列表',
     *     'param'  => ''
     * )
     */
    public function index()
    {
        $goodsCateModel = new GoodsCateModel();
        $categoryTree        = $goodsCateModel->adminCategoryTableTree();

        $this->assign('category_tree', $categoryTree);
        return $this->fetch();
    }
	
	/**
     * 入库单列表
     * @adminMenu(
     *     'name'   => '品类管理',
     *     'parent' => 'portal/GoodsCate/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '入库单列表',
     *     'param'  => ''
     * )
     */
	public function indexin(){
		return $this->_getList();
    }
	
	public function indexout(){
		return $this->_getList('indexout');
    }
	
	private function _getList($tmp = 'indexin'){
		$where = ['children'=> '0']; 
		$data = Db::name('GoodsCate')
				->where($where)
				->order("list_order")
				->paginate(10);
		
        $this->assign('data', $data);
        $this->assign('page', $data->render());
        return $this->fetch('goods_cate/' . $tmp);
	}

    /**
     * 添加品类分类
     * @adminMenu(
     *     'name'   => '添加品类分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加品类分类',
     *     'param'  => ''
     * )
     */
    public function add()
    {
        $parentId            = $this->request->param('parent', 0, 'intval');
        $goodsCateModel = new GoodsCateModel();
        $categoriesTree      = $goodsCateModel->adminCategoryTree($parentId);

        $themeModel        = new ThemeModel();
        $cateThemeFiles    = $themeModel->getActionThemeFiles('portal/Cate/index');
        $goodsThemeFiles = $themeModel->getActionThemeFiles('portal/Goods/index');

        $this->assign('cate_theme_files', $cateThemeFiles);
        $this->assign('goods_theme_files', $goodsThemeFiles);
        $this->assign('categories_tree', $categoriesTree);
        return $this->fetch();
    }

    /**
     * 添加品类分类提交
     * @adminMenu(
     *     'name'   => '添加品类分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加品类分类提交',
     *     'param'  => ''
     * )
     */
    public function addPost()
    {
        $goodsCateModel = new GoodsCateModel();

        $data = $this->request->param();

        $result = $this->validate($data, 'GoodsCate');

        if ($result !== true) {
            $this->error($result);
        }

        $result = $goodsCateModel->addCategory($data);

        if ($result === false) {
            $this->error('添加失败!');
        }

        $this->success('添加成功!', url('GoodsCate/index'));

    }

    /**
     * 编辑品类分类
     * @adminMenu(
     *     'name'   => '编辑品类分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑品类分类',
     *     'param'  => ''
     * )
     */
    public function edit()
    {
        $id = $this->request->param('id', 0, 'intval');
        if ($id > 0) {
            $category = GoodsCateModel::get($id)->toArray();

            $goodsCateModel = new GoodsCateModel();
            $categoriesTree      = $goodsCateModel->adminCategoryTree($category['parent_id'], $id);

            $this->assign($category);
            $this->assign('categories_tree', $categoriesTree);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }

    }

    /**
     * 编辑品类分类提交
     * @adminMenu(
     *     'name'   => '编辑品类分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑品类分类提交',
     *     'param'  => ''
     * )
     */
    public function editPost()
    {
        $data = $this->request->param();

        $result = $this->validate($data, 'GoodsCate');

        if ($result !== true) {
            $this->error($result);
        }

        $goodsCateModel = new GoodsCateModel();

        $result = $goodsCateModel->editCategory($data);

        if ($result === false) {
            $this->error('保存失败!');
        }

        $this->success('保存成功!', url('GoodsCate/index'));
    }
	
    public function inedit(){
        return $this->_edit();

    }
	public function outedit(){
        return $this->_edit('outedit');

    }
	private function _edit($tmp = 'inedit'){
		$id = $this->request->param('id', 0, 'intval');
        if ($id > 0) {
            $category = GoodsCateModel::get($id)->toArray();
            $this->assign($category);
            return $this->fetch('goods_cate/' . $tmp);
        } else {
            $this->error('操作错误!');
        }
	}

    public function ineditPost(){
        $this->_editPost();
    }
	public function outeditPost(){
        $this->_editPost('outdexin');
    }
	private function _editPost($tmp = 'indexin'){
		$data = $this->request->param();
        $result = Db::name('GoodsCate')->update($data);

        if ($result === false) {
            $this->error('保存失败!');
        }
        $this->success('保存成功!', url('GoodsCate/' . $tmp));
		
	}
	
	/**
     * 上线
     */
	public function online()
    {
        $param           = $this->request->param();
		$type = trim($param["type"]);

        if (isset($param['ids']) && in_array($type, ['in', 'out'])) {
            $ids = $this->request->param('ids/a');

            Db::name('GoodsCate')->where(['id' => ['in', $ids]])->update([$type . '_status' => 1]);
            $this->success("操作成功！", '');
        }

    }
	
	/**
     * 下线
     */
	public function offline()
    {
        $param	= $this->request->param();
		$type = trim($param["type"]);

        if (isset($param['ids']) && in_array($type, ['in', 'out'])) {
            $ids = $this->request->param('ids/a');

            Db::name('GoodsCate')->where(['id' => ['in', $ids]])->update([$type . '_status' => 0]);
            $this->success("操作成功！", '');
        }

    }

    /**
     * 品类分类选择对话框
     * @adminMenu(
     *     'name'   => '品类分类选择对话框',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '品类分类选择对话框',
     *     'param'  => ''
     * )
     */
    public function select()
    {
        $ids                 = $this->request->param('ids');
        $selectedIds         = explode(',', $ids);
        $goodsCateModel = new GoodsCateModel();

        $tpl = <<<tpl
<tr class='data-item-tr'>
    <td>
        <input type='checkbox' class='js-check' data-yid='js-check-y' data-xid='js-check-x' name='ids[]'
               value='\$id' data-name='\$name' \$checked>
    </td>
    <td>\$id</td>
    <td>\$spacer <a href='\$url' target='_blank'>\$name</a></td>
</tr>
tpl;

        $categoryTree = $goodsCateModel->adminCategoryTableTree($selectedIds, $tpl);

        $where      = ['delete_time' => 0];
        $categories = $goodsCateModel->where($where)->select();

        $this->assign('categories', $categories);
        $this->assign('selectedIds', $selectedIds);
        $this->assign('categories_tree', $categoryTree);
        return $this->fetch();
    }

    /**
     * 品类分类排序
     * @adminMenu(
     *     'name'   => '品类分类排序',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '品类分类排序',
     *     'param'  => ''
     * )
     */
    public function listOrder()
    {
        parent::listOrders(Db::name('goods_cate'));
        $this->success("排序更新成功！", '');
    }

    /**
     * 删除品类分类
     * @adminMenu(
     *     'name'   => '删除品类分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '删除品类分类',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $goodsCateModel = new GoodsCateModel();
        $id                  = $this->request->param('id');
        //获取删除的内容
        $findCategory = $goodsCateModel->where('id', $id)->find();

        if (empty($findCategory)) {
            $this->error('分类不存在!');
        }
//判断此分类有无子分类（不算被删除的子分类）
        $categoryChildrenCount = $goodsCateModel->where(['parent_id' => $id,'delete_time' => 0])->count();

        if ($categoryChildrenCount > 0) {
            $this->error('此分类有子类无法删除!');
        }

        /* $categoryPostCount = Db::name('portal_category_post')->where('category_id', $id)->count();

        if ($categoryPostCount > 0) {
            $this->error('此分类有品类无法删除!');
        } */

        $data   = [
            'object_id'   => $findCategory['id'],
            'create_time' => time(),
            'table_name'  => 'goods_cate',
            'name'        => $findCategory['name']
        ];
        $result = $goodsCateModel
            ->where('id', $id)
            ->update(['delete_time' => time()]);
        if ($result) {
            Db::name('recycleBin')->insert($data);
            $this->success('删除成功!');
        } else {
            $this->error('删除失败');
        }
    }
	
	public function stats(){
		$where['parent_id'] = ['neq', 0];
		$data = DB::name('goods_cate')->where($where)->select()->toArray();
		$total = 0;
		
		foreach($data as $k=>$v){
			$data[$k]['total'] = $v['stock'] * $v['settlement_out_price'];
			$total += $v['stock'] * $v['settlement_out_price'];
		}

		$this->assign('data', $data);
		$this->assign('total', $total);
		return $this->fetch();
	}
}
