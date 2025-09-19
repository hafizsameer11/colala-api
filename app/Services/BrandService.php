<?php 

namespace App\Services;

use App\Models\Brand;

class BrandService
{
    public function getAll()
    {
        return Brand::all();
    }

    public function create(array $data)
    {
        if (isset($data['logo'])) {
            $data['logo'] = $data['logo']->store('brands', 'public');
        }
        return Brand::create($data);
    }

    public function update(int $id, array $data)
    {
        $brand = Brand::findOrFail($id);

        if (isset($data['logo'])) {
            $data['logo'] = $data['logo']->store('brands', 'public');
        }

        $brand->update($data);
        return $brand;
    }

    public function delete(int $id)
    {
        $brand = Brand::findOrFail($id);
        return $brand->delete();
    }
}
