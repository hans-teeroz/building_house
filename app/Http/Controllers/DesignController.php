<?php

namespace App\Http\Controllers;

use App\Models\Attribute;
use App\Models\Product;
use App\Models\Room;
use App\Models\Wishlist;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DesignController extends FrontendController
{
    /**
     * @inheritDoc
     */
    protected function getService()
    {
        // TODO: Implement getService() method.
    }

    public function index(Request $request)
    {
        $rooms = services()->roomService()->where('active', Room::STATUS_PUBLIC)->doesntHave('parent')
            ->with(['childs' => function ($query) {
                $query->with(['attributes' => function ($query) {
                    $query->where('active', Attribute::STATUS_PUBLIC);
                }]);
                $query->where('active', Room::STATUS_PUBLIC);
            }])->select('id', 'title')->get();
        $item = 3;
        if ($request->slug) {
            $item = 2;
            $product = services()->productService()->where([
                'active' => Product::ACTIVE
            ])->where(function ($query) use ($request) {
                if (isset($request->category_id) && $request->category_id > 0) {
                    $query->where('category_id', $request->category_id);
                }
            })->where('slug', $request->slug)->select('id', 'title', 'arr_image', 'image_back_ground_design')->first();
        }

        $products = services()->productService()->where([
            'active' => Product::ACTIVE
        ])->where(function ($query) use ($request) {
            if (isset($request->category_id) && $request->category_id > 0) {
                $query->where('category_id', $request->category_id);
            }
            if (isset($request->slug)) {
                $query->where('slug', '<>', $request->slug);
            }
        })->select('id', 'title', 'arr_image', 'image_back_ground_design')->orderByDesc('id')->paginate($item);
//        dd($products, $product);
        $viewData = [
            'rooms' => $rooms,
            'products' => $products,
            'productSelect' => $product ?? null
        ];
        return view('design.index', $viewData);
    }

    public function store(Request $request)
    {
        $data = $request->data;
//        && get_data_user('web')
        if (trim(get_data_user('web')) === '') {
            return response()->json(['status' => false, 'message' => 'Vui lòng đăng nhập để sử dụng chức năng này!']);
        }
        if ($data) {
            $creator_id =  get_data_user('web') ?? $data['creator_id'];
            $product_id = $data['product_id'] ?? null;
            if (!$product_id) {
                return response()->json(['status' => false, 'message' => 'Vui lòng chọn sản phẩm!']);
            }

            $arr_system = $data['arr_system'] ?? [];
            $arr_style = $data['arr_style'] ?? [];
            if (!isset($data['code'])) {
                if (count($arr_system) < 1) {
                    return response()->json(['status' => false, 'message' => 'Vui lòng chọn thuộc tính tiện nghi sản phẩm!']);
                }
                if (count($arr_style) < 1) {
                    return response()->json(['status' => false, 'message' => 'Vui lòng chọn thuộc tính kiểu dáng sản phẩm!']);
                }
            }

            $newSystem = [];
            foreach ($arr_system as $item) {
                $newSystem[$item['attribute_id']][] = $item['key'];
            }
            $newStyle = [];
            foreach ($arr_style as $item) {
                $newStyle[$item['attribute_id']] = ['key_choose' => json_encode([$item['key']])];
            }
            DB::beginTransaction();
            try {
                if (isset($data['code'])) {
                    $wishlist = Wishlist::where([
                        'creator_id' => $creator_id,
    //                    'type' => Wishlist::TYPE_WISHLIST,
                        'title' => $data['code']
                    ])->first();
                    if (!$wishlist) {
                        return response()->json(['status' => false, 'message' => 'Lỗi cập nhật!']);
                    }
                    $wishlist->type = $wishlist->type === Wishlist::TYPE_WISHLIST ? $data['type'] : $wishlist->type;
                    if ($wishlist->type == Wishlist::TYPE_WISHLIST) {
                        $message = 'Đã lưu vào danh sách của bạn!';
                    } else {
                        $message = 'Thiết kế của bạn đã gửi cho chúng tôi! Chúng tôi sẽ liện hệ bạn sớm nhất!';
                    }
                    if ($wishlist->save()) {
                        if (count($newStyle) > 0 || count($newSystem) > 0) {
                            $syncData = $newStyle;
                            foreach ($newSystem as $key => $item) {
                                $syncData[$key] = ['key_choose' => json_encode($item)];
                            }
                            $changes = $wishlist->attributes()->whereIn('attribute_id', array_keys($syncData))->get();
//                            dd($changes);
                            $change_ids = [];
                            foreach ($changes as $change) {
                                if ($change->type == Attribute::TYPE_SYSTEM && in_array($change->attribute_id, array_keys($syncData))) {
                                    $arr1 = json_decode($change->key_choose);
                                    $arr2 = json_decode($syncData[$change->attribute_id]['key_choose']);
                                    $sames = array_intersect($arr1, $arr2);
                                    $_arr = array_unique(array_merge_recursive($arr1, $arr2));
                                    foreach ($_arr as $key => $item) {
                                        if (in_array($item, $sames)) {
                                            unset($_arr[$key]);
                                        }
                                    }
                                    $syncData[$change->attribute_id]['key_choose'] = json_encode(array_values($_arr));
                                }
                                $change_ids [] = $change->id;
                            }
                            DB::table('wishlists_attributes')->where('wishlist_id', $wishlist->id)->whereIn('attribute_id', $change_ids)->delete();
                            //TODO: fix lại lỗi update system giữ lại value trong mảng trước khi xóa còn style thì ok r
                            $wishlist->attributes()->syncWithoutDetaching($syncData);
                        }
                    }
                } else {
                    $wishlist = new Wishlist();
                    $wishlist->product_id = $product_id;
                    $wishlist->creator_id = $creator_id;
                    $wishlist->title = substr(md5(microtime().rand()), -10);
                    $wishlist->status = Wishlist::STATUS_NOT_FINISHED;
                    $wishlist->type = $data['type'] ?? Wishlist::TYPE_WISHLIST;
                    if ($wishlist->type == Wishlist::TYPE_WISHLIST) {
                        $message = 'Đã thêm vào danh sách của bạn!';
                    } else {
                        $message = 'Thiết kế của bạn đã gửi cho chúng tôi! Chúng tôi sẽ liện hệ bạn sớm nhất!';
                    }
                    if ($wishlist->save()) {
                        $syncData = $newStyle;
                        foreach ($newSystem as $key => $item) {
                            $syncData[$key] = ['key_choose' => json_encode($item)];
                        }
                        if (count($syncData) > 0) {
                            $wishlist->attributes()->sync($syncData);
                        }
                    }
                }
                DB::commit();
                return response()->json(['status' => true, 'message' => $message]);
            } catch (Exception $e) {
                DB::rollback();
                return response()->json(['status' => false, 'message' => $this->errorResult($e)]);
            }
        }
        return response()->json(['status' => false, 'message' => 'Đã xảy ra lỗi vui lòng thử lại!']);
    }

    public function listWishlist()
    {
        $user_id = get_data_user('web');
        $wishLists = services()->wishlistService()->where([
            'creator_id' =>$user_id,
//            'type' => Wishlist::TYPE_WISHLIST,
//            'status',
        ])->with('product')->orderByDesc('id')->paginate(12);
        $viewData = [
            'wishLists' => $wishLists,
        ];
        return view('wishlist.index', $viewData);
    }

    public function detailWishlist($slug, Request $request)
    {
        $user_id = get_data_user('web');

        if ($request->code && $slug) {
            $rooms = services()->roomService()->where('active', Room::STATUS_PUBLIC)->doesntHave('parent')
                ->with(['childs' => function ($query) use ($user_id, $request, $slug) {
                    $query->with(['attributes' => function ($query) use ($user_id, $request, $slug) {
                        $query->with(['wishlists' => function ($query) use ($user_id, $request, $slug) {
//                            $query->whereHas('product', function ($query) use ($slug) {
//                                $query->where('slug', $slug);
//                            });
                            $query->where([
                                'creator_id' => $user_id,
//                                'type' => Wishlist::TYPE_WISHLIST, // nếu chỉ lấy wishlist thì mở cmt ra
                                'title' => $request->code
                            ]);
                        }]);
                        $query->where('active', Attribute::STATUS_PUBLIC);
                    }]);
                    $query->where('active', Room::STATUS_PUBLIC);
                }])->select('id', 'title')->get();

            $wishList = services()->wishlistService()->where([
                'creator_id' => $user_id,
//                'type' => Wishlist::TYPE_WISHLIST, // nếu chỉ lấy wishlist thì mở cmt ra
                'title' => $request->code
            ])->whereHas('product', function ($query) use ($slug) {
                $query->where('slug', $slug);
            })->with([
                'product' => function ($query) use ($slug) {
                    $query->where('slug', $slug);
                },
                'attributes' => function ($query) {
                    $query->where('active', Attribute::STATUS_PUBLIC);
                }])
                ->first();
//            dd($wishList);
            if (!$wishList) {
                return redirect()->back();
            }
            $viewData = [
                'wishList' => $wishList,
                'rooms' => $rooms
            ];
            return view('wishlist.detail', $viewData);
        }
        return redirect()->back();
    }
}
