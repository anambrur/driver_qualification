<div class="max-w-3xl mx-auto mt-10">
    <!-- Tabs -->
    <div class="border-b border-gray-200">
        <nav class="flex space-x-6" aria-label="Tabs">
            <button
                class="tab-btn border-b-2 border-indigo-500 text-indigo-600 py-3 text-sm font-medium"
                data-tab="tab1">
                Profile
            </button>

            <button
                class="tab-btn border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 text-sm font-medium"
                data-tab="tab2">
                Settings
            </button>

            <button
                class="tab-btn border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 py-3 text-sm font-medium"
                data-tab="tab3">
                Security
            </button>
        </nav>
    </div>

    <!-- Tab Contents -->
    <div class="mt-6">
        <div id="tab1" class="tab-content">
            <h3 class="text-lg font-semibold mb-2">Profile</h3>
            <p class="text-gray-600">This is profile content.</p>
        </div>

        <div id="tab2" class="tab-content hidden">
            <h3 class="text-lg font-semibold mb-2">Settings</h3>
            <p class="text-gray-600">This is settings content.</p>
        </div>

        <div id="tab3" class="tab-content hidden">
            <h3 class="text-lg font-semibold mb-2">Security</h3>
            <p class="text-gray-600">This is security content.</p>
        </div>
    </div>
</div>


<script>
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            // Reset buttons
            tabButtons.forEach(btn => {
                btn.classList.remove('border-indigo-500', 'text-indigo-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Hide contents
            tabContents.forEach(content => content.classList.add('hidden'));

            // Activate current tab
            button.classList.remove('border-transparent', 'text-gray-500');
            button.classList.add('border-indigo-500', 'text-indigo-600');

            document.getElementById(button.dataset.tab).classList.remove('hidden');
        });
    });
</script>
