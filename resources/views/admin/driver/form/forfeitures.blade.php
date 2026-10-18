<x-ui.card id="section-forfeitures" title="Forfeitures, previous 3 years" class="scroll-mt-24">
    <div class="grid gap-6">
        <x-ui.radio-group name="denied_license" legend="A. Has the driver ever been denied a license, permit or privilege to operate a motor vehicle?" :value="$v['denied_license']" required />
        <x-ui.radio-group name="license_revoked" legend="B. Has any license, permit or privilege ever been suspended or revoked?" :value="$v['license_revoked']" required />
        <x-ui.field label="If yes to A or B, explain" name="forfeitures">
            <x-ui.textarea name="forfeitures" rows="3" :value="$v['forfeitures']" maxlength="1000" class="w-full" />
        </x-ui.field>
    </div>
</x-ui.card>
