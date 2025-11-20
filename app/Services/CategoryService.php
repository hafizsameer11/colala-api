<?php 

namespace App\Services;

use App\Models\Category;

class CategoryService{
    public function create($data){
        //check if data has image
        if($data['image']){
            $path = $data['image']->store('category', 'public');
            $data['image'] = $path;
        }
        return Category::create($data);
    }
    /**
     * Update a category by its ID.
     *
     * @param int $id
     * @param array $data
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function update($id, $data){
        $category=Category::find($id);
        if(isset($data['image']) && $data['image']){
            $path = $data['image']->store('category', 'public');
            $data['image'] = $path;
        }
        $category->update($data);
        return $category;
    }
    public function getAll(){
        return Category::all();
    }
    public function delete($id){
        return Category::find($id)->delete();
    }

}