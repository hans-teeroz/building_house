<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\WebController;
use App\Models\Attribute;
use App\Models\Category;
use App\Services\AttributeService;
use Cviebrock\EloquentSluggable\Services\SlugService;
use Illuminate\Http\Request;

class AdminAttributeController extends WebController
{
    /**
     * @inheritDoc
     */
    protected function getService(): AttributeService
    {
        return services()->attributeService();
    }

    protected function getRequest()
    {
//        return c(AdminRequest::class);
        return request();
    }

    public function __list(Request $request, $view = null)
    {
        $request->merge([
            '_attribute_fields' => 'title,type,room_id,avatar,active,author_id,arr_value,arr_image,arr_active',
            '_relations' => 'creator,room',
            '_admin_fields' => 'name',
            '_room_fields' => 'title',
//            '_noPagination' => 1,
//            '_filter' => 'user_not_myself:1;'
        ]);
        return parent::__list($request, 'admin::attribute.index');
//        return view('admin::category.index', $viewData);
    }

    public function __create(Request $request, $route = null)
    {
        return parent::__create($request, 'admin.get.list.attribute');
//        return view('admin::category.index', $viewData);
    }

    public function __find(Request $request, $is_json = false)
    {
        $request->merge([
            '_attribute_fields' => 'title,type,room_id,avatar,active,author_id,arr_value,arr_image,arr_active',
            '_relations' => 'creator,room',
            '_room_fields' => 'title',
        ]);
        return parent::__find($request, true);
    }

    public function __update($id, $route = null)
    {
        return parent::__update($id, 'admin.get.list.attribute');
//        return view('admin::category.index', $viewData);
    }

    public function action($action, $id)
    {
        $messages = '';
        if ($action) {
            $attribute = Attribute::find($id);
            switch ($action) {
                case 'delete':
                    $attribute->delete();
                    //TODO: xoa file ra khoi sourse
                    $messages = 'Xóa thành công!';
                    break;
                case 'active':
                    $attribute->active = $attribute->active ? 0 : 1;
                    $attribute->save();
                    $messages = 'Cập nhật thành công!';
                    break;
                case 'hot':
                    $attribute->hot = $attribute->hot ? 0 : 1;
                    $attribute->save();
                    $messages = 'Cập nhật thành công!';
                    break;
            }
        }
        return redirect()->back()->with('success', $messages);
    }

    public function checkSlug(Request $request)
    {
        $slug = SlugService::createslug(Category::class, 'slug', $request->title);
        return response()->json(['slug' => $slug]);
    }

    public static function showCategories($categories, $parent_id = null, $char = '')
    {
        foreach ($categories as $key => $item) {
            if ($item['parent_id'] == $parent_id) {
                echo '<option value="'.$item['id'].'">';
                echo $char . $item['title'];
                echo '</option>';

                unset($categories[$item['id']]);

                self::showCategories($categories, $item['id'], $char.'&nbsp&nbsp&nbsp&nbsp&nbsp');
            }
        }
    }
}