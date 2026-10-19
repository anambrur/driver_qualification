{{-- Image preview for a compliance document; previewImage() in page-script fills and opens it. --}}
<x-ui.dialog id="imagePreviewModal" title="Preview" size="xl">
    <img id="imagePreviewImage" alt="" class="mx-auto max-h-[70vh] max-w-full rounded-md object-contain">
    <x-slot:footer>
        <x-ui.button variant="outline" data-action="close-dialog">Close</x-ui.button>
        <x-ui.button id="imagePreviewDownload" href="#" icon="download" download>Download</x-ui.button>
    </x-slot:footer>
</x-ui.dialog>
