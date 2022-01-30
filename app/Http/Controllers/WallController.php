<?php

namespace App\Http\Controllers;

use App\Models\AllTag;
use App\Models\AllWallTag;
use App\Models\Wall;
use Illuminate\Http\Request;

use function PHPUnit\Framework\isEmpty;

class WallController extends Controller
{


    public function index(Request $request, $category = null)
    {
        $category = strtolower($category);

        $walls = Wall::query();

        if ($category != null) {
            $walls->whereRaw("JSON_CONTAINS(lower(JSON_EXTRACT(categories, '$')), '\"{$category}\"')");
        }

        if ($request->has('s')) {

            $query = strtolower($request->s);

            $orderByString = "case
                when tags LIKE '\"$query%' then 1
                when categories LIKE '%$query%' then 2
                when tags LIKE '%$query%' then 3 ";
            $counter = 4;

            global $orderByArray;
            $orderByArray = [];
            $walls->where(function ($whereQuery) use ($query) {

                global $orderByArray;
                $whereQuery->where('categories', 'like', '%' . $query . '%');
                $whereQuery->orWhere('tags', 'like', '%' . $query . '%');

                $subQueries = explode(' ', $query, 3);

                foreach ($subQueries as $q) {
                    $whereQuery->orWhere('categories', 'like', '%' . $q . '%');
                    $whereQuery->orWhere('tags', 'like', '%' . $q . '%');
                    $orderByArray[] =
                        [
                            " when tags LIKE '\"$q%' then ",
                            " when categories LIKE '%$q%' then ",
                            " when tags LIKE '%$q%'  then "
                        ];
                }
            });
            if (!isEmpty($orderByArray)) {

                $orderByArr = array_map(null, ...$orderByArray);

                foreach ($orderByArr as $value) {
                    foreach ($value as $str) {
                        $orderByString .= $str . ' ' . $counter++;
                    }
                }
            }
            $walls->orderByRaw($orderByString . " else $counter end");
        }

        if ($request->order_by == 'downloads') {
            $walls->orderBy('downloads', "DESC");
        }
        $walls->orderBy('created_at', "DESC");
        return $walls->paginate();
    }

    public function category($category)
    {
        $category = strtolower($category);
        // where json array contains id in categories column
        $walls = Wall::whereRaw("JSON_CONTAINS(lower(JSON_EXTRACT(categories, '$')), '\"{$category}\"')")->paginate();

        return $walls;
    }


    public function store(Request $request)
    {
        $data = $request->validate([
            'source' => 'required|max:255|unique:walls',
            'color' => 'required|max:10',

            'urls' => 'required|array',
            'urls.full' => 'required|max:255',
            'urls.small' => 'required|max:255',
            'urls.raw' => 'nullable|max:255',
            'urls.regular' => 'nullable|max:255',


            'tags' => 'required|array',
            'tags.*' => 'required|max:50',

            'categories' => 'required|array',
            'categories.*' => 'required|string|exists:all_tags,name,type,category',

            'colors' => 'required|array',
            'colors.*' => 'required|string|exists:all_tags,name,type,color',

            'license' => 'nullable|max:255',
            'author' => 'nullable|max:100',
            'author_portfolio' => 'nullable|max:255',
            'author_image' => 'nullable|max:255',
            'coins' => 'nullable|integer',
        ]);

        $allTags = [];

        foreach ($data['tags'] as $tag) {
            $allTags[] = AllTag::firstOrCreate(['name' => $tag, 'type' => 'tag'])->all_tag_id;
        }

        foreach ($data['categories'] as $category) {
            $allTags[] = AllTag::firstOrCreate(['name' => $category, 'type' => 'category'])->all_tag_id;
        }

        foreach ($data['colors'] as $color) {
            $allTags[] = AllTag::firstOrCreate(['name' => $color, 'type' => 'color'])->all_tag_id;
        }


        $wall = new Wall();
        $wall->source = $data['source'];
        $wall->color = $data['color'];
        $wall->urls = $data['urls'];
        $wall->license = $data['license'];
        $wall->author = $data['author'];
        $wall->author_portfolio = $data['author_portfolio'];
        $wall->author_image = $data['author_image'];
        if (isset($data['coins'])) {
            $wall->coins = $data['coins'];
        }
        $wall->save();

        AllWallTag::insert(array_map(function ($tag) use ($wall) {
            return ['wall_id' => $wall->wall_id, 'all_tag_id' => $tag];
        }, $allTags));

        return response()->json(['message' => 'Wallpaper added successfully']);
    }

    /* private function findInAllTags($name, $type)
    {
        $tag = AllTag::where('name', $name)->where('type', $type)->first();
        if ($tag == null) return null;
        return $tag->all_tag_id;
    } */

    // delete wallpaper
    public function destroy($id)
    {
        $wall = Wall::find($id);
        if ($wall != null) {
            $wall->delete();
            return response()->json(['message' => 'Wallpaper deleted successfully']);
        }
        return response()->json(['message' => 'No Wallpaper found with given id'], 404);
    }

    public function download($id)
    {
        $wall = Wall::where('id', $id)->increment('downloads');
        if ($wall) {
            return response()->json(['message' => 'Wallpaper downloaded successfully']);
        }
        return response()->json(['message' => 'No Wallpaper found with given id'], 404);
    }
}
