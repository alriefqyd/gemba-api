<?php

namespace App\Http\Controllers;

use App\Models\Findings;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PhpOffice\PhpPresentation\DocumentLayout;
use PhpOffice\PhpPresentation\IOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Style\Alignment;
use PhpOffice\PhpPresentation\Style\Color;
use PhpOffice\PhpPresentation\Style\Fill;

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

            $project = Project::create([
                'project_title' => $request->project_title,
                'project_no' => $request->project_no,
                'project_area' => $request->project_area,
                'images' => "null.jpg",
                'created_by' => auth()->user()->id,
                'updated_by' => auth()->user()->id,
            ]);

            foreach ($request->findings as $f) {
                $imagePath = null;

                if (isset($f['image']) && $f['image']->isValid()) {
                    $imagePath = $f['image']->store('findings_images', 'public');  // Store image in the 'public/findings_images' directory
                }

                $project->findings()->create([
                    'finding_type' => $f['finding_type'],
                    'date' => $f['date'],
                    'image' => $imagePath,
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

    public function generatePptx(Project $project)
    {
        // Create a new PowerPoint presentation object
        $ppt = new PhpPresentation();

        // Define the desired aspect ratio dimensions
        $width = 960;  // Width in EMUs (20 cm)

        // Set the slide dimensions
        $ppt->getLayout()->setDocumentLayout(DocumentLayout::LAYOUT_SCREEN_16X9);

        // Create the first slide
        $slide1 = $ppt->getActiveSlide();

        // Set background image for the first slide
        $bgImagePath = storage_path('app/public/bg_slide.png'); // Path to background image
        if (file_exists($bgImagePath)) {
            $bgShape = $slide1->createDrawingShape();
            $bgShape->setPath($bgImagePath)
                ->setWidth($width)
                ->setHeight(540) // Set this according to your slide height requirement
                ->setOffsetX(0)
                ->setOffsetY(0)
                ->setResizeProportional(true); // Resize proportionally
        }

        // Add title to the slide with specified coordinates
        $titleShape = $slide1->createRichTextShape()
            ->setHeight(100)
            ->setWidth(380)
            ->setOffsetX(580) // Set left offset to 12.3 EMUs
            ->setOffsetY(100); // Set top offset to 2.5 EMUs
        $titleShape->getActiveParagraph()->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $titleRun = $titleShape->createTextRun($project->project_title);
        $titleRun->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFF'));

        // Continue with other content...

        // Slide dimensions and padding for layout
        $slideWidth = 960; // Typical width for a slide in PowerPoint
        $sectionWidth = ($slideWidth / 3) - 20; // Each section takes a third minus some padding
        $imageHeight = 100; // Adjusted height for better proportionality

        // Add project details to the first slide
//        $detailsShape = $slide1->createRichTextShape()
//            ->setHeight(300)
//            ->setWidth(600)
//            ->setOffsetX(50)
//            ->setOffsetY(150);
//        $detailsShape->createTextRun("Project No: " . $project->project_no . "\n")
//            ->getFont()->setSize(18);
//        $detailsShape->createTextRun("Project Area: " . $project->project_area . "\n")
//            ->getFont()->setSize(18);

        // Convert findings collection to array and chunk it into groups of 3
        $findings = $project->findings->toArray();
        $chunks = array_chunk($findings, 3); // Group findings in sets of 3 per slide

        // Loop over each chunk to create slides
        foreach ($chunks as $chunk) {
            // Create a new slide for each set of findings
            $newSlide = $ppt->createSlide();

            // Offset variables for positioning each finding in the slide
            $xOffset = 20;
            $yOffset = 50;

            foreach ($chunk as $finding) {
                // Add the "Finding" title with green background, taking the full section width
                $titleShape = $newSlide->createRichTextShape()
                    ->setHeight(40)
                    ->setWidth($sectionWidth)
                    ->setOffsetX($xOffset)
                    ->setOffsetY($yOffset);
                $titleShape->getFill()->setFillType(Fill::FILL_SOLID)->setStartColor(new Color('FF008080'));
                $titleTextRun = $titleShape->createTextRun("FINDING");
                $titleTextRun->getFont()->setBold(true)->setSize(16)->setColor(new Color('FFFFFFFF'));

                // Add image proportionally scaled below the "FINDING" title
                $imageYOffset = $yOffset + 50; // Offset below the title
                if (!empty($finding['image'])) {
                    $imagePath = storage_path('app/public/' . $finding['image']);
                    if (file_exists($imagePath)) {
                        $imageShape = $newSlide->createDrawingShape();
                        $imageShape->setPath($imagePath);

                        // Set image dimensions proportional to the section
                        $imageShape->setWidth($sectionWidth)
                            ->setHeight(4.45 * 28.35) // Set height to 4.45 cm (1 cm = 28.35 points)
                            ->setOffsetX($xOffset) // Center align within section
                            ->setOffsetY($imageYOffset);
                    }
                }

                // Add finding details below the image
                $detailsYOffset = $imageYOffset + (4.45 * 28.35) + 10; // Space between image and details
                $detailsShape = $newSlide->createRichTextShape()
                    ->setHeight(200)
                    ->setWidth($sectionWidth)
                    ->setOffsetX($xOffset)
                    ->setOffsetY($detailsYOffset);

                $detailsShape->createTextRun("Date: " . ($finding['date'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(12);
                $detailsShape->createTextRun("Area: " . ($finding['area'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(12);
                $detailsShape->createTextRun("Supervisor: " . ($finding['supervisor'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(12);
                $detailsShape->createTextRun("Safety Officer: " . ($finding['safety_officer'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(12);

                // Add detailed description and status
                $detailsShape->createTextRun("\nDetailed Description:\n")
                    ->getFont()->setBold(true)->setSize(12);
                $detailsShape->createTextRun("Finding: " . ($finding['finding_description'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(10);
                $detailsShape->createTextRun("Action: " . ($finding['action'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(10);
                $detailsShape->createTextRun("Status: " . ($finding['status'] ?? 'N/A') . "\n")
                    ->getFont()->setSize(10);

                // Update xOffset for the next finding in the same row
                $xOffset += $sectionWidth + 10; // Include padding between sections
            }
        }

        // Save the presentation to a temporary file
        $fileName = 'project_' . $project->id . '_generated.pptx';
        $tempFile = storage_path('app/public/' . $fileName);

        $oWriterPPTX = IOFactory::createWriter($ppt, 'PowerPoint2007');
        $oWriterPPTX->save($tempFile);

        // Return the file as a download
        return response()->download($tempFile)->deleteFileAfterSend(true);
    }








}
