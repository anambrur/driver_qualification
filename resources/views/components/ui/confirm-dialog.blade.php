{{--
    One shared confirm step for plain forms, rendered once by the layout:
        <form data-confirm="Delete this plan?" data-confirm-text="..." data-confirm-button="Delete">
    resources/js/app.js opens it on submit and submits the form only on confirm.
--}}
<x-ui.dialog id="confirm-dialog" size="sm" icon="triangle-alert" title="Please confirm" aria-describedby="confirm-dialog-text">
    <p id="confirm-dialog-text" class="text-sm text-muted-foreground"></p>
    <x-slot:footer>
        <form method="dialog" class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <x-ui.button type="submit" variant="outline" value="cancel">Cancel</x-ui.button>
            <x-ui.button type="submit" variant="destructive" value="ok" data-confirm-ok>Confirm</x-ui.button>
        </form>
    </x-slot:footer>
</x-ui.dialog>
