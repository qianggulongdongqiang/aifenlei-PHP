<?php
namespace app\portal\model;

use app\admin\model\RouteModel;
use think\Model;
use tree\Tree;

class GoodsCateModel extends Model{


    /**
     * 生成分类 select树形结构
     * @param int $selectId 需要选中的分类 id
     * @param int $currentCid 需要隐藏的分类 id
     * @return string
     */
    public function adminCategoryTree($selectId = 0, $currentCid = 0)
    {
        $where = ['delete_time' => 0];
        if (!empty($currentCid)) {
            $where['id'] = ['neq', $currentCid];
        }
        $categories = $this->order("list_order ASC")->where($where)->select()->toArray();

        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;│', '&nbsp;&nbsp;├─', '&nbsp;&nbsp;└─'];
        $tree->nbsp = '&nbsp;&nbsp;';

        $newCategories = [];
        foreach ($categories as $item) {
            $item['selected'] = $selectId == $item['id'] ? "selected" : "";

            array_push($newCategories, $item);
        }

        $tree->init($newCategories);
        $str     = '<option value=\"{$id}\" {$selected}>{$spacer}{$name}</option>';
        $treeStr = $tree->getTree(0, $str);

        return $treeStr;
    }

    /**
     * @param int|array $currentIds
     * @param string $tpl
     * @return string
     */
    public function adminCategoryTableTree($currentIds = 0, $tpl = '')
    {
        $where = ['delete_time' => 0];
//        if (!empty($currentCid)) {
//            $where['id'] = ['neq', $currentCid];
//        }
        $categories = $this->order("list_order ASC")->where($where)->select()->toArray();

        $tree       = new Tree();
        $tree->icon = ['&nbsp;&nbsp;│', '&nbsp;&nbsp;├─', '&nbsp;&nbsp;└─'];
        $tree->nbsp = '&nbsp;&nbsp;';

        if (!is_array($currentIds)) {
            $currentIds = [$currentIds];
        }
		
		$member_type = ['1'=>'普通居民', '2'=>'回收员居民'];

        $newCategories = [];
        foreach ($categories as $item) {
            $item['checked'] = in_array($item['id'], $currentIds) ? "checked" : "";
            $item['url']     = cmf_url('portal/GoodsCate/index', ['id' => $item['id']]);
			$item['str_action'] = '';
			if($item['parent_id'] == 0){
				$item['str_action'] = '<a href="' . url("GoodsCate/add", ["parent" => $item['id']]) . '">添加子分类</a> ';  
			}
			
			$item['str_action'] .= '<a href="' . url("GoodsCate/edit", ["id" => $item['id']]) . '">' . lang('EDIT') . '</a>  ';
			
			if($item['is_machine'] != 1){
				$item['str_action'] .= '<a class="js-ajax-delete" href="' . url("GoodsCate/delete", ["id" => $item['id']]) . '">' . lang('DELETE') . '</a>';
			}
			$item['img_1'] = empty($item['img_1']) ? '' : '<img src="'.cmf_get_image_preview_url($item['img_1']).'" height="50" />';
			$item['img_2'] = empty($item['img_2']) ? '' : '<img src="'.cmf_get_image_preview_url($item['img_2']).'" height="50" />';
			$item['member_type'] = $member_type[$item['member_type']];
			if($item['is_machine'] == 1){
				$item['name'] .= '(自助)';
				if($item['parent_id'] == 25 || $item['id'] == 25){
					$item['str_action'] .= ' <a href="' . url("GoodsCateCode/index", ["id" => $item['id']]) . '">条码管理</a>';
				}
			}
            array_push($newCategories, $item);
        }

        $tree->init($newCategories);

        if (empty($tpl)) {
            $tpl = "<tr>
                        <td><input name='list_orders[\$id]' type='text' size='3' value='\$list_order' class='input-order'></td>
                        <td>\$id</td>
                        <td>\$spacer <a href='\$url' target='_blank'>\$name</a></td>
						<td>\$unit_name ：\$unit</td>
						<td>\$img_1</td>
						<td>\$img_2</td>
						<td>\$member_type</td>
                        <td>\$str_action</td>
                    </tr>";
        }
        $treeStr = $tree->getTree(0, $tpl);

        return $treeStr;
    }

    /**
     * 添加品类分类
     * @param $data
     * @return bool
     */
    public function addCategory($data){
        $result = true;
        self::startTrans();
        try {
            $this->allowField(true)->save($data);
            $id = $this->id;
            if (empty($data['parent_id'])) {

                $this->where(['id' => $id])->update(['path' => '0-' . $id]);
            } else {
                $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
                $this->where(['id' => $id])->update(['path' => "$parentPath-$id"]);
				$this->where(['id' => $data['parent_id']])->setInc('children');

            }
            self::commit();
        } catch (\Exception $e) {
            self::rollback();
            $result = false;
        }
        return $result;
    }

    public function editCategory($data)
    {
        $result = true;

        $id          = intval($data['id']);
        $parentId    = intval($data['parent_id']);
        $oldCategory = $this->where('id', $id)->find();

        if (empty($parentId)) {
            $newPath = '0-' . $id;
        } else {
            $parentPath = $this->where('id', intval($data['parent_id']))->value('path');
            if ($parentPath === false) {
                $newPath = false;
            } else {
                $newPath = "$parentPath-$id";
            }
        }

        if (empty($oldCategory) || empty($newPath)) {
            $result = false;
        } else {

            $data['path'] = $newPath;
            $this->isUpdate(true)->allowField(true)->save($data, ['id' => $id]);		
			
            $children = $this->field('id,path')->where('path', 'like', $oldCategory['path'] . "-%")->select();
            if (!$children->isEmpty()) {
                foreach ($children as $child) {
                    $childPath = str_replace($oldCategory['path'] . '-', $newPath . '-', $child['path']);
                    $this->where('id', $child['id'])->update(['path' => $childPath], ['id' => $child['id']]);
                }
            }
			
			//更新父节点children
			$old_children_count = $this->where('parent_id', $oldCategory['parent_id'])->count();
			$this->where('id', $oldCategory['parent_id'])->setField('children', intval($old_children_count));
			$new_children_count = $this->where('parent_id', $parentId)->count();
			$this->where('id', $parentId)->setField('children', intval($new_children_count));
        }
        return $result;
    }

	public function getInfoByPid($pid = 0, $field = 'id,name,unit,purchasing_price,purchasing_point', $order='list_order'){
		return $this->where('parent_id', $pid)->field($field)->order($order)->select()->toArray();
	}
	
	public function getInfo($condition = array(), $field = '*'){
		return $this->where($condition)->field($field)->find();
	}
}