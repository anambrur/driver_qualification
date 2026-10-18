<x-ui.card id="section-experience" title="Driving experience" description="Set Yes for each equipment class the driver has driven, with the dates and approximate miles." class="scroll-mt-24">
    <div class="overflow-x-auto max-md:overflow-visible max-md:[&_td_.input]:max-w-[60%] max-md:[&_td_.select]:max-w-[60%]">
        <table class="table table-stack">
            <thead>
                <tr>
                    <th scope="col">Equipment class</th>
                    <th scope="col">Experience</th>
                    <th scope="col">From</th>
                    <th scope="col">To</th>
                    <th scope="col">Approx. miles</th>
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Support\Driver\DriverForm::EQUIPMENT_CLASSES as $index => $equipmentClass)
                    @php($experience = $v['experiences'][$index])
                    <tr>
                        <td data-primary class="font-medium">
                            {{ $equipmentClass }}
                            <input type="hidden" name="equipment_class[{{ $index }}]" value="{{ $equipmentClass }}">
                        </td>
                        <td data-label="Experience">
                            <x-ui.select name="experience[{{ $index }}]" :options="['no' => 'No', 'yes' => 'Yes']" :value="$experience['experience']" aria-label="{{ $equipmentClass }} experience" class="min-w-24" />
                        </td>
                        <td data-label="From">
                            <x-ui.input type="date" name="experience_from_date[{{ $index }}]" :value="$experience['from']" aria-label="{{ $equipmentClass }} from" />
                        </td>
                        <td data-label="To">
                            <x-ui.input type="date" name="experience_to_date[{{ $index }}]" :value="$experience['to']" aria-label="{{ $equipmentClass }} to" />
                        </td>
                        <td data-label="Approx. miles">
                            <x-ui.input type="number" min="0" step="1" name="approx_miles[{{ $index }}]" :value="$experience['miles']" placeholder="0" aria-label="{{ $equipmentClass }} approximate miles" class="min-w-28" />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</x-ui.card>
