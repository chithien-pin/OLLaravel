<?php

namespace App\Http\Controllers;

use App\Models\DiamondPacks;
use Illuminate\Http\Request;

class DiamondPackController extends Controller
{
    //

    function diamondpacks()
    {
        return view('diamondpacks');
    }

    function getDiamondPacks(Request $request){
        $data = DiamondPacks::all();

        return json_encode([
            'status' => true,
            'message' => 'diamond packs get successfully',
            'data' => $data
        ]);
    }

    public function addDiamondPack(Request $request)
    {
        // Validate image if uploaded
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);
        }

        $pack = new DiamondPacks();
        $pack->amount = $request->amount;
        $pack->android_product_id = $request->android_product_id;
        $pack->ios_product_id = $request->ios_product_id;

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/diamond_packs'), $imageName);
            $pack->image = 'images/diamond_packs/' . $imageName;
        }

        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Added Successfully',
        ]);
    }

    function getDiamondPackById($id)
    {
        $data = DiamondPacks::where('id', $id)->first();
        echo response()->json($data);
    }

    function updateDiamondPack(Request $request)
    {
        // Validate image if uploaded
        if ($request->hasFile('image')) {
            $request->validate([
                'image' => 'image|mimes:jpeg,jpg,png|max:2048', // Max 2MB
            ]);
        }

        $pack = DiamondPacks::where('id', $request->id)->first();
        $pack->amount = $request->amount;
        $pack->android_product_id = $request->android_product_id;
        $pack->ios_product_id = $request->ios_product_id;

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($pack->image && file_exists(public_path($pack->image))) {
                unlink(public_path($pack->image));
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path('images/diamond_packs'), $imageName);
            $pack->image = 'images/diamond_packs/' . $imageName;
        }

        $pack->save();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Updated Successfully',
        ]);
    }

    function deleteDiamondPack(Request $request)
    {
        $diamondPack = DiamondPacks::where('id', $request->diamond_pack_id)->first();

        // Delete image file if exists
        if ($diamondPack->image && file_exists(public_path($diamondPack->image))) {
            unlink(public_path($diamondPack->image));
        }

        $diamondPack->delete();

        return response()->json([
            'status' => true,
            'message' => 'Diamond Pack Deleted',
        ]);
    }

    function fetchDiamondPackages(Request $request)
    {
        $totalData =  DiamondPacks::count();
        $rows = DiamondPacks::orderBy('id', 'DESC')->get();

        $result = $rows;

        $columns = array(
            0 => 'id',
            1 => 'amount'
        );

        $limit = $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');
        $totalData = DiamondPacks::count();
        $totalFiltered = $totalData;
        if (empty($request->input('search.value'))) {
            $result = DiamondPacks::offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $result =  DiamondPacks::Where('amount', 'LIKE', "%{$search}%")
                                    ->orWhere('android_product_id', 'LIKE', "%{$search}%")
                                    ->orWhere('ios_product_id', 'LIKE', "%{$search}%")
                                    ->offset($start)
                                    ->limit($limit)
                                    ->orderBy($order, $dir)
                                    ->get();
            $totalFiltered = DiamondPacks::where('amount', 'LIKE', "%{$search}%")
                                    ->orWhere('android_product_id', 'LIKE', "%{$search}%")
                                    ->orWhere('ios_product_id', 'LIKE', "%{$search}%")
                                    ->count();
        }
        $data = array();
        foreach ($result as $item) {

            // Image preview
            $imageHtml = '';
            if ($item->image) {
                $imageUrl = asset($item->image);
                $imageHtml = '<img src="'.$imageUrl.'" alt="Diamond Pack" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">';
            } else {
                $imageHtml = '<span class="text-muted">No image</span>';
            }

            $block = '<span class="float-end">
                            <a href="" rel="' . $item->id . '"
                                class="btn btn-success edit mr-2"
                                data-amount="'. $item->amount .'"
                                data-android_product_id="'. $item->android_product_id .'"
                                data-ios_product_id="'. $item->ios_product_id .'"
                                data-image="'. ($item->image ?? '') .'">
                                Edit
                            </a>
                            <a rel="'.$item->id.'" class="btn btn-danger delete text-white">
                                Delete
                            </a>
                        </span>';

            $data[] = array(
                $item->amount,
                $item->android_product_id,
                $item->ios_product_id,
                $imageHtml,
                $block
            );
        }
        $json_data = array(
            "draw"            => intval($request->input('draw')),
            "recordsTotal"    => intval($totalData),
            "recordsFiltered" => $totalFiltered,
            "data"            => $data
        );
        echo json_encode($json_data);
        exit();
    }
}
