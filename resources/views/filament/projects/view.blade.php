<x-filament::page>
    <div class="space-y-6">
        <!-- Project Details Card -->
        <x-filament::card>
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-800">Project Details</h2>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500">Project Name</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $record->project_title }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Project No</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $record->project_no }}</p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-500">Area</p>
                        <p class="text-lg font-semibold text-gray-900">{{ $record->project_area }}</p>
                    </div>
                </div>
            </div>
            <div class="space-y-4">
                <a href="/project/{{$record->id}}/pptx" class="" target="_blank"><p class="text-green-500">Download Report</p></a>
            </div>
        </x-filament::card>

        <!-- Findings Card -->
        <x-filament::card>
            <div class="space-y-4">
                <h2 class="text-xl font-semibold text-gray-800">Findings</h2>
                <ul class="space-y-4">
                    @foreach ($record->findings as $finding)
                        <li class="flex items-start space-x-4 border border-gray-200 rounded-lg p-4 bg-gray-50 hover:bg-gray-100 transition">
                            <!-- Larger Image Section -->
                            <div class="rounded-lg overflow-hidden shadow-md" style="width: 30%">
                                <img
                                    src="{{ url(Storage::url($finding->image ?? 'default.png')) }}"
                                    alt="Finding Image"
                                    class="w-full h-full object-cover"
                                >
                            </div>

                            <!-- Text Section -->
                            <div class="flex-1 space-y-1">
                                <p class="text-lg font-semibold text-gray-800">{{ $finding->finding_type }}</p>

                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 font-semibold">Date</p>
                                        <p class="text-sm text-gray-700 leading-tight">{{ $finding->date }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 font-semibold">Supervisor</p>
                                        <p class="text-sm text-gray-700 leading-tight">{{ $finding->supervisor }}</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-600 font-semibold">Safety Officer</p>
                                        <p class="text-sm text-gray-700 leading-tight">{{ $finding->safety_officer }}</p>
                                    </div>
                                </div>

                                <p class="text-sm font-medium text-gray-600 font-semibold">Finding Description</p>
                                <p class="text-sm text-gray-700 leading-tight">{{ $finding->finding_description }}</p>

                                <p class="text-sm font-medium text-gray-600 font-semibold">Action Description</p>
                                <p class="text-sm text-gray-700 leading-tight">{{ $finding->action_description }}</p>

                                <p class="text-sm font-medium text-gray-600 font-semibold">Status</p>
                                <p class="text-sm font-semibold text-green-600 leading-tight">{{ $finding->status }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </x-filament::card>
    </div>
</x-filament::page>
