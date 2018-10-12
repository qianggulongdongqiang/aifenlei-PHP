<?php
namespace app\portal\controller;

use cmf\controller\AdminBaseController;
use think\Db;
use app\admin\model\ThemeModel;

class GoodsController extends AdminBaseController{

    public function index(){

		
		$data = Db::name('goods')
				->order("id DESC")
				->paginate(10);
				

        $this->assign('data', $data);
        $this->assign('page', $data->render());


        return $this->fetch();
    }

    public function add(){
        return $this->fetch();
    }

    public function addPost(){
        if ($this->request->isPost()) {
            $data   = $this->request->param();

            $result = $this->validate($data, 'Goods');
            if ($result !== true) {
                $this->error($result);
            }

			$result = Db::name('Goods')->insert($data);
			
			if($result)	{
				$this->success('添加成功!', url('Goods/index'));
			}else{
				$this->error('添加失败!');	
			}
        }

    }

    public function edit(){
        $id = $this->request->param('id', 0, 'intval');
        if ($id > 0) {
            $data = Db::name('goods')->where('id', $id)->find();

            $this->assign($data);
            return $this->fetch();
        } else {
            $this->error('操作错误!');
        }
    }

    public function editPost()
    {

        if ($this->request->isPost()) {
            $data   = $this->request->param();

            $result = $this->validate($data, 'Goods');

            if ($result !== true) {
                $this->error($result);
            }

            $result = Db::name('Goods')->update($data);

            $this->success('保存成功!');

        }
    }

    public function delete()
    {
        $param           = $this->request->param();

        if (isset($param['id'])) {
            $id           = $this->request->param('id', 0, 'intval');
            $result       = $portalPostModel->where(['id' => $id])->find();
            $data         = [
                'object_id'   => $result['id'],
                'create_time' => time(),
                'table_name'  => 'portal_post',
                'name'        => $result['post_title'],
                'user_id'=>cmf_get_current_admin_id()
            ];
            $resultPortal = $portalPostModel
                ->where(['id' => $id])
                ->update(['delete_time' => time()]);
            if ($resultPortal) {
                Db::name('portal_category_post')->where(['post_id'=>$id])->update(['status'=>0]);
                Db::name('portal_tag_post')->where(['post_id'=>$id])->update(['status'=>0]);

                Db::name('recycleBin')->insert($data);
            }
            $this->success("删除成功！", '');

        }

        if (isset($param['ids'])) {
            $ids     = $this->request->param('ids/a');
            $recycle = $portalPostModel->where(['id' => ['in', $ids]])->select();
            $result  = $portalPostModel->where(['id' => ['in', $ids]])->update(['delete_time' => time()]);
            if ($result) {
                Db::name('portal_category_post')->where(['post_id' => ['in', $ids]])->update(['status'=>0]);
                Db::name('portal_tag_post')->where(['post_id' => ['in', $ids]])->update(['status'=>0]);
                foreach ($recycle as $value) {
                    $data = [
                        'object_id'   => $value['id'],
                        'create_time' => time(),
                        'table_name'  => 'portal_post',
                        'name'        => $value['post_title'],
                        'user_id'=>cmf_get_current_admin_id()
                    ];
                    Db::name('recycleBin')->insert($data);
                }
                $this->success("删除成功！", '');
            }
        }
    }

 
    public function online()
    {
        $param           = $this->request->param();

        if (isset($param['ids'])) {
            $ids = $this->request->param('ids/a');

            Db::name('goods')->where(['id' => ['in', $ids]])->update(['status' => 1]);

            $this->success("上线成功！", '');
        }

    }

   
    public function offline()
    {
        $param           = $this->request->param();

        if (isset($param['ids'])) {
            $ids = $this->request->param('ids/a');

            Db::name('goods')->where(['id' => ['in', $ids]])->update(['status' => 0]);

            $this->success("下线成功！", '');
        }

    }




}
