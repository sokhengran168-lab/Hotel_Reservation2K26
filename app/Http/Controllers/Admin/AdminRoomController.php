<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Api\Upload\UploadApi;
use Cloudinary\Api\Admin\AdminApi;

class AdminRoomController extends Controller
{
    
    private function configureCloudinary(): void
    {
        $url = config('services.cloudinary.url') ?: env('CLOUDINARY_URL');

        if (!$url) {
            throw new \RuntimeException('CLOUDINARY_URL is not set. Check your environment configuration.');
        }

        Configuration::instance($url);
    }

    public function index()
    {
        $rooms = Room::paginate(15);
        return view('admin.rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('admin.rooms.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'number'      => 'required|string|unique:rooms,number',
            'type'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'capacity'    => 'required|integer|min:1',
            'features'    => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'images.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data['features'] = $data['features']
            ? array_values(array_filter(array_map('trim', explode(',', $data['features']))))
            : null;

        $this->configureCloudinary();
        $uploadApi = new UploadApi();

        // Main image fallback from uploaded images
        if ($request->hasFile('image')) {
            $result = $uploadApi->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'rooms']
            );
            $data['image'] = $result['secure_url'];
        }

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                $result = $uploadApi->upload(
                    $file->getRealPath(),
                    ['folder' => 'rooms']
                );
                $paths[] = $result['secure_url'];
            }
            $data['images'] = $paths;
            if (empty($data['image'])) {
                $data['image'] = $paths[0] ?? null;
            }
        }

        Room::create($data);

        return redirect()->route('admin.rooms.index')->with('success', 'Room created successfully.');
    }

    public function show(string $id)
    {
        $room = Room::findOrFail($id);
        return view('admin.rooms.show', compact('room'));
    }

    public function edit(string $id)
    {
        $room = Room::findOrFail($id);
        return view('admin.rooms.edit', compact('room'));
    }

    public function update(Request $request, string $id)
    {
        $room = Room::findOrFail($id);

        $data = $request->validate([
            'number'      => 'required|string|unique:rooms,number,' . $room->id,
            'type'        => 'required|string',
            'description' => 'nullable|string',
            'price'       => 'required|numeric|min:0',
            'capacity'    => 'required|integer|min:1',
            'features'    => 'nullable|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'images.*'    => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $data['features'] = $data['features']
            ? array_values(array_filter(array_map('trim', explode(',', $data['features']))))
            : null;

        $this->configureCloudinary();
        $uploadApi = new UploadApi();

        // Main image
        if ($request->hasFile('image')) {
            $this->deleteCloudinaryImage($room->image);
            $result = $uploadApi->upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'rooms']
            );
            $data['image'] = $result['secure_url'];
        }

        if ($request->hasFile('images')) {
            if ($room->images) {
                foreach ($room->images as $old) {
                    $this->deleteCloudinaryImage($old);
                }
            }

            $this->deleteCloudinaryImage($room->image);

            $paths = [];
            foreach ($request->file('images') as $file) {
                $result = $uploadApi->upload(
                    $file->getRealPath(),
                    ['folder' => 'rooms']
                );
                $paths[] = $result['secure_url'];
            }
            $data['images'] = $paths;
            $data['image'] = $paths[0] ?? null;
        }

        $room->update($data);

        return redirect()->route('admin.rooms.index')->with('success', 'Room updated successfully.');
    }

    public function destroy(string $id)
    {
        $room = Room::findOrFail($id);

        $this->deleteCloudinaryImage($room->image);

        if ($room->images) {
            foreach ($room->images as $img) {
                $this->deleteCloudinaryImage($img);
            }
        }

        $room->delete();

        return redirect()->route('admin.rooms.index')->with('success', 'Room deleted successfully.');
    }

   
    
    private function deleteCloudinaryImage(?string $url): void
    {
        if (!$url || !str_contains($url, 'res.cloudinary.com')) {
            return;
        }

       
        if (preg_match('#/upload/(?:v\d+/)?(.+)\.\w+$#', $url, $matches)) {
            $publicId = $matches[1];
            try {
                $this->configureCloudinary();
                $uploadApi = new UploadApi();
                $uploadApi->destroy($publicId);
            } catch (\Exception $e) {
               
                report($e);
            }
        }
    }
}