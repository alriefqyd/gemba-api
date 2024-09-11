<?php

namespace App\Http\Controllers;

use App\Models\Findings;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ProjectController extends Controller
{
    public function index(){
        $data = Project::with(['findings'])->get();
        return response()->json([
            'status' => 200,
            'data' => $data
        ]);
    }

    public function store(Request $request) {
        $request->validate([
            'project_title' => 'required|string|max:255',
            'project_no' => 'required|string|max:255',
            'project_area' => 'required|string|max:255',
            'findings' => 'required|array',
            'findings.*.action_description' => 'required|string',
            'findings.*.finding_description' => 'required|string',
            'findings.*.image' => 'nullable|file|mimes:jpeg,png,jpg,gif'  // Add image validation
        ]);

        try {
            DB::beginTransaction();

            // Create a new project
            $project = Project::create([
                'project_title' => $request->project_title,
                'project_no' => $request->project_no,
                'project_area' => $request->project_area,
                'images' => "null.jpg"  // Set project images to null initially
            ]);

            // Handle each finding and upload the image
            foreach ($request->findings as $f) {
                $imagePath = null;

//                 Check if image is uploaded
                if (isset($f['image']) && $f['image']->isValid()) {
                    $imagePath = $f['image']->store('findings_images', 'public');  // Store image in the 'public/findings_images' directory
                }

                // Save finding along with the image path
                $project->findings()->create([
                    'finding_type' => $f['finding_type'],
                    'date' => $f['date'],
                    'image' => $imagePath,  // Save the image path in the database
                    'safety_officer' => $f['safety_officer'],
                    'supervisor' => $f['supervisor'],
                    'finding_description' => $f['finding_description'],
                    'action_description' => $f['action_description'],
                    'status' => $f['status']
                ]);
            }

            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Project and findings saved successfully!',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 422,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function update(Request $request, Project $project)
    {
        // Log the request input
        Log::info('Update request received:', $request->all());

        // Validate the request
        $request->validate([
            'project_title' => 'required',
            'project_no' => 'required',
            'project_area' => 'required',
            'findings' => 'required|array',
        ]);

        try {
            // Update project fields
            $project->project_title = $request->project_title;
            $project->project_no = $request->project_no;
            $project->project_area = $request->project_area;
            $project->images = 'image.jpg'; // Placeholder for project image handling
            $project->save();

            // Fetch existing findings related to this project
            $existingFindings = $project->findings()->pluck('id')->toArray();

            // Track the IDs of the findings sent in the request
            $updatedFindingIds = [];

            // Loop through findings and either update or create new ones
            foreach ($request->findings as $findingData) {
                $imagePath = null;

                if (isset($findingData['id'])) {
                    // Update existing finding
                    $finding = Findings::where('id', $findingData['id'])->first();
                    if ($finding) {
                        // Store the old image path to delete it later if a new image is uploaded
                        $oldImage = $finding->image;

                        $finding->finding_type = $findingData['finding_type'];
                        $finding->date = $findingData['date'];
                        $finding->safety_officer = $findingData['safety_officer'];
                        $finding->action_description = $findingData['action_description'];
                        $finding->finding_description = $findingData['finding_description'];
                        $finding->status = $findingData['status'];
                        $finding->supervisor = $findingData['supervisor'];

                        // Handle image upload if present
                        if (isset($findingData['image']) && $findingData['image'] instanceof \Illuminate\Http\UploadedFile) {
                            // Upload the new image
                            $imagePath = $findingData['image']->store('findings_images', 'public');

                            // If there was an old image, delete it
                            if ($oldImage) {
                                Storage::disk('public')->delete($oldImage);
                            }

                            // Set the new image path
                            $finding->image = $imagePath;
                        }

                        $finding->save();
                        $updatedFindingIds[] = $finding->id;
                    }
                } else {
                    // Create new finding
                    $newFinding = new Findings();
                    $newFinding->finding_type = $findingData['finding_type'];
                    $newFinding->date = $findingData['date'];
                    $newFinding->safety_officer = $findingData['safety_officer'];
                    $newFinding->action_description = $findingData['action_description'];
                    $newFinding->finding_description = $findingData['finding_description'];
                    $newFinding->status = $findingData['status'];
                    $newFinding->supervisor = $findingData['supervisor'];
                    $newFinding->project_id = $project->id;

                    // Handle image upload if present
                    if (isset($findingData['image']) && $findingData['image'] instanceof \Illuminate\Http\UploadedFile) {
                        $imagePath = $findingData['image']->store('findings_images', 'public');
                        $newFinding->image = $imagePath;
                    }

                    $newFinding->save();
                    $updatedFindingIds[] = $newFinding->id;
                }
            }

            // Delete findings that were not in the updated request
            $findingsToDelete = array_diff($existingFindings, $updatedFindingIds);
            foreach ($findingsToDelete as $findingId) {
                $finding = Findings::find($findingId);

                // Check if the finding has an image and delete it from storage
                if ($finding && $finding->image) {
                    Storage::disk('public')->delete($finding->image);  // Deletes the image from the storage
                }

                // Delete the finding from the database
                $finding->delete();
            }

            return response()->json(['status' => 200, 'message' => 'Project and findings updated successfully.']);
        } catch (\Exception $e) {
            Log::error('An error occurred:', [$e->getMessage()]);
            return response()->json(['status' => 500, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }






    public function delete(Project $project){
        DB::beginTransaction();
        try {
            foreach ($project->findings as $f){
                Storage::disk('public')->delete($f->image);  //
            }
            $project->delete();
            DB::commit();
            return response()->json([
                'status' => 200,
                'message' => 'Successfully Deleted'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 500,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function show(Project $project){
        $project = $project->load('findings');
        return response()->json([
            'status' => 200,
            'data' => $project
        ]);
    }

}
