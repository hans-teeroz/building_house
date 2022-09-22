<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\WebController;
use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Http\Request;

class AdminSettingController extends WebController
{
    protected function getRequest()
    {
        return request();
    }

    /**
     * @inheritDoc
     */
    protected function getService(): SettingService
    {
        return services()->settingService();
    }

    public function __list(Request $request, $view = null)
    {
        $request->merge([
            '_setting_fields' => 'name,key,value,active,icon,type',
            '_noPagination' => 1,
            '_orderBy' => 'id:asc',
        ]);
        return parent::__list($request, 'admin::setting.index');
    }

    public function update(Request $request)
    {
        $result = $this->getService()->updateKeySeting($request->all());
        return response()->json($result);
//        return view('admin::category.index', $viewData);
    }

    public function updateSettingHome(Request $request)
    {
        $result = $this->getService()->updateSettingHome($request);
        if ($result) {
            return redirect()->back()->with('success', 'Cập nhật thành công!');
        }
        return redirect()->back()->with('danger', 'Thêm mới thất bại!');
    }

    public function listSetting(Request $request)
    {
        $request->merge([
            '_setting_fields' => 'name,key,value,active,icon',
            '_noPagination' => 1,
            '_orderBy' => 'id:asc',
        ]);
        return response()->json($this->getService()->getMany($request));
    }

    public function action($action, $id)
    {
        $messages = '';
        if ($action) {
            $post = Setting::find($id);
            switch ($action) {
                case 'delete':
                    $post->delete();
                    //TODO: xoa file ra khoi sourse
                    $messages = 'Xóa thành công!';
                    break;
                case 'active':
                    $post->active = $post->active ? 0 : 1;
                    $post->save();
                    $messages = 'Cập nhật thành công!';
                    break;
            }
        }
        return response()->json(['success' => $messages]);
    }
}