<x-ui.card title="Specifications">
    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.detail label="Equipment type" :value="$trailer->equipmentType?->name" />
        <x-ui.detail label="Vehicle group" :value="$trailer->vehicleGroup?->name" />
        <x-ui.detail label="Owned by" :value="$trailer->owned_by" />
        <x-ui.detail label="Color" :value="$trailer->color" />
        <x-ui.detail label="Title number" :value="$trailer->title_no" />
        <x-ui.detail label="GVW" :value="$trailer->gvw ? number_format($trailer->gvw).' lbs' : null" />
        <x-ui.detail label="Tire size" :value="$trailer->tire_size" />
        <x-ui.detail label="Notes" :value="$trailer->notes" class="whitespace-pre-line sm:col-span-2 lg:col-span-4" />
    </dl>
</x-ui.card>
