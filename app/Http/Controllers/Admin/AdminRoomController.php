<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class AdminRoomController extends Controller
{
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

        // Main image fallback from uploaded images
        if ($request->hasFile('image')) {
            $data['image'] = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'rooms']
            )->getSecurePath();
        }

        if ($request->hasFile('images')) {
            $paths = [];
            foreach ($request->file('images') as $file) {
                $paths[] = Cloudinary::upload(
                    $file->getRealPath(),
                    ['folder' => 'rooms']
                )->getSecurePath();
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

        // Main image
        if ($request->hasFile('image')) {
            $this->deleteCloudinaryImage($room->image);
            $data['image'] = Cloudinary::upload(
                $request->file('image')->getRealPath(),
                ['folder' => 'rooms']
            )->getSecurePath();
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
                $paths[] = Cloudinary::upload(
                    $file->getRealPath(),
                    ['folder' => 'rooms']
                )->getSecurePath();
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

    /**
     * Delete an image from Cloudinary given its full secure URL.
     * Safely does nothing if the URL isn't a Cloudinary URL (e.g. old
     * local storage paths from before the migration to Cloudinary).
     */
    private function deleteCloudinaryImage(?string $url): void
    {
        if (!$url || !str_contains($url, 'res.cloudinary.com')) {
            return;
        }

        // Example URL:
        // https://res.cloudinary.com/pcx0peif/image/upload/v1234567890/rooms/abcde12345.jpg
        // We need to extract: rooms/abcde12345
        if (preg_match('#/upload/(?:v\d+/)?(.+)\.\w+$#', $url, $matches)) {
            $publicId = $matches[1];
            try {
                Cloudinary::destroy($publicId);
            } catch (\Exception $e) {
                // Log and continue — don't block the request if Cloudinary
                // deletion fails (e.g. network hiccup, already deleted).
                report($e);
            }
        }
    }
}