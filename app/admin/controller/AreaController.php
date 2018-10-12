<?php
namespace app\admin\controller;

use app\admin\model\RouteModel;
use cmf\controller\AdminBaseController;
use app\admin\model\AreaModel;
use think\Db;
use app\admin\model\ThemeModel;


class AreaController extends AdminBaseController
{
    /**
     * 区域分类列表
     * @adminMenu(
     *     'name'   => '区域管理',
     *     'parent' => 'admin/Area/default',
     *     'display'=> true,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '区域分类列表',
     *     'param'  => ''
     * )
     */
    public function index(){
        $areaModel = new AreaModel();
        $categoryTree = $areaModel->adminCategoryTableTree();

        $this->assign('category_tree', $categoryTree);
        return $this->fetch();
    }
	

    /**
     * 添加区域分类
     * @adminMenu(
     *     'name'   => '添加区域分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加区域分类',
     *     'param'  => ''
     * )
     */
    public function add(){
        $parentId            = $this->request->param('parent', 0, 'intval');
        $areaModel = new AreaModel();
        $categoriesTree      = $areaModel->adminCategoryTree($parentId);

        $this->assign('categories_tree', $categoriesTree);
        return $this->fetch();
    }

    /**
     * 添加区域分类提交
     * @adminMenu(
     *     'name'   => '添加区域分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '添加区域分类提交',
     *     'param'  => ''
     * )
     */
    public function addPost(){
        $areaModel = new AreaModel();

        $data = $this->request->param();

        $result = $this->validate($data, 'Area');

        if ($result !== true) {
            $this->error($result);
        }

        $result = $areaModel->addCategory($data);

        if ($result === false) {
            $this->error('添加失败!');
        }

        $this->success('添加成功!', url('Area/index'));

    }

    /**
     * 编辑区域分类
     * @adminMenu(
     *     'name'   => '编辑区域分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> true,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑区域分类',
     *     'param'  => ''
     * )
     */
    public function edit(){
        $id = $this->request->param('id', 0, 'intval');
        if ($id > 0) {
            $category = AreaModel::get($id)->toArray();

            $areaModel = new AreaModel();
            $categoriesTree      = $areaModel->adminCategoryTree($category['parent_id'], $id);

            $this->assign($category);
            $this->assign('categories_tree', $categoriesTree);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }

    }

    /**
     * 编辑区域分类提交
     * @adminMenu(
     *     'name'   => '编辑区域分类提交',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '编辑区域分类提交',
     *     'param'  => ''
     * )
     */
    public function editPost(){
        $data = $this->request->param();

        $result = $this->validate($data, 'Area');

        if ($result !== true) {
            $this->error($result);
        }

        $areaModel = new AreaModel();

        $result = $areaModel->editCategory($data);

        if ($result === false) {
            $this->error('保存失败!');
        }

        $this->success('保存成功!', url('Area/index'));
    }

    /**
     * 区域分类排序
     * @adminMenu(
     *     'name'   => '区域分类排序',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '区域分类排序',
     *     'param'  => ''
     * )
     */
    public function listOrder(){
        parent::listOrders(Db::name('area'));
        $this->success("排序更新成功！", '');
    }

    /**
     * 删除区域分类
     * @adminMenu(
     *     'name'   => '删除区域分类',
     *     'parent' => 'index',
     *     'display'=> false,
     *     'hasView'=> false,
     *     'order'  => 10000,
     *     'icon'   => '',
     *     'remark' => '删除区域分类',
     *     'param'  => ''
     * )
     */
    public function delete()
    {
        $areaModel = new AreaModel();
        $id                  = $this->request->param('id');
        //获取删除的内容
        $findCategory = $areaModel->where('id', $id)->find();

        if (empty($findCategory)) {
            $this->error('分类不存在!');
        }
		//判断此分类有无子分类（不算被删除的子分类）
        $categoryChildrenCount = $areaModel->where(['parent_id' => $id,'delete_time' => 0])->count();

        if ($categoryChildrenCount > 0) {
            $this->error('此分类有子类无法删除!');
        }


        $data   = [
            'object_id'   => $findCategory['id'],
            'create_time' => time(),
            'table_name'  => 'area',
            'name'        => $findCategory['name']
        ];
        $result = $areaModel
            ->where('id', $id)
            ->update(['delete_time' => time()]);
        if ($result) {
            Db::name('recycleBin')->insert($data);
            $this->success('删除成功!');
        } else {
            $this->error('删除失败');
        }
    }
	
	public function user(){
        $id = $this->request->param('id', 0, 'intval');
		
		if ($this->request->isPost()) {
			
			$master = $this->request->param('master', 0, 'intval');
			$result = Db::name('area')->where(['id'=>$id])->setField('master', $master);
			
			if ($result === false) {
				$this->error('保存失败!');
			}
			
			//处理回收员
			$user = input('post.user/a');
			DB::name('area_user')->where(['area_id'=>$id])->delete();
			foreach($user as $v){
				$insert[] = ['area_id'=>$id, 'user_id'=>$v];
			}
			DB::name('area_user')->insertAll($insert);

			$this->success('保存成功!', url('Area/index'));
		}
		
        if ($id > 0) {
            $category = AreaModel::get($id)->toArray();

            $user = DB::name('user')->where(['user_type'=>3])->field('id,user_nickname,user_login')->select();
			
			$collector = [];
			
			foreach(DB::name('area_user')->where(['area_id'=>$id])->field('user_id')->select() as $v){
				$collector[$v['user_id']] = $v['user_id'];
			}

            $this->assign($category);
            $this->assign('user', $user);
			$this->assign('collector', $collector);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }

    }
}
