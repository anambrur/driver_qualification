@extends('layouts.main-layout')

@section('title', 'Investment Calculator')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-200 mb-2">Investment Calculator</h1>
            <p class="text-gray-600 dark:text-gray-400">Calculate and compare compound vs simple interest profits</p>
        </div>

        <!-- Single Input Form and Results -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <!-- Form Section -->
            <div class="md:col-span-3">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight mb-6">
                        {{ __('Investment Parameters') }}
                    </h2>

                    <form id="investmentCalculator" class="space-y-6">
                        @csrf

                        <!-- Initial Amount -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Initial Investment Amount (৳)
                            </label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">৳</span>
                                </div>
                                <input type="number" id="amount" name="amount" min="0" step="0.01"
                                    value="1000"
                                    class="pl-7 w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    required>
                            </div>
                        </div>

                        <!-- Number of Months -->
                        <div>
                            <label for="months" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Investment Duration (Months)
                            </label>
                            <input type="range" id="monthsRange" name="monthsRange" min="1" max="60"
                                value="12"
                                class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer mb-2">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <span>1 month</span>
                                <span>5 years</span>
                            </div>
                            <input type="number" id="months" name="months" min="1" max="360" value="12"
                                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                required>
                        </div>

                        <!-- Monthly Profit Percentage -->
                        <div>
                            <label for="percentage" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Monthly Profit Rate (%)
                            </label>
                            <input type="range" id="percentageRange" name="percentageRange" min="0.1" max="20"
                                step="0.1" value="8.5"
                                class="w-full h-2 bg-gray-200 dark:bg-gray-700 rounded-lg appearance-none cursor-pointer mb-2">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-3">
                                <span>0.1%</span>
                                <span>20%</span>
                            </div>
                            <div class="relative">
                                <input type="number" id="percentage" name="percentage" min="0.1" max="100"
                                    step="0.1" value="8.5"
                                    class="pr-7 w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                                    required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 dark:text-gray-400 sm:text-sm">%</span>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Presets -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Quick Presets
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" data-percentage="5"
                                    class="py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md text-sm transition">5%</button>
                                <button type="button" data-percentage="8.5"
                                    class="py-2 px-3 bg-blue-100 dark:bg-blue-900 hover:bg-blue-200 dark:hover:bg-blue-800 rounded-md text-sm transition">8.5%</button>
                                <button type="button" data-percentage="12"
                                    class="py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md text-sm transition">12%</button>
                                <button type="button" data-percentage="15"
                                    class="py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md text-sm transition">15%</button>
                                <button type="button" data-percentage="18"
                                    class="py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md text-sm transition">18%</button>
                                <button type="button" data-percentage="20"
                                    class="py-2 px-3 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 rounded-md text-sm transition">20%</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Results Section -->
            <div class="space-y-6 md:col-span-9">
                <!-- Comparison Summary -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                        <h2 class="font-semibold text-2xl text-gray-800 dark:text-gray-200 leading-tight mb-4 md:mb-0">
                            Investment Comparison
                        </h2>
                        <div class="flex space-x-2">
                            <span
                                class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                Compound Interest
                            </span>
                            <span
                                class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Simple Interest
                            </span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Compound Interest Card -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-md border border-blue-200 dark:border-blue-900">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">With Reinvestment</h3>
                                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Final Amount</p>
                                    <p class="text-3xl font-bold text-blue-600 dark:text-blue-400" id="compoundFinalAmount">
                                        ৳2,660.02</p>
                                </div>
                                <div
                                    class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Profit</p>
                                        <p class="text-xl font-semibold text-green-600 dark:text-green-400"
                                            id="compoundTotalProfit">৳1,660.02</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Avg Monthly</p>
                                        <p class="text-lg font-semibold text-blue-600 dark:text-blue-400"
                                            id="compoundAvgMonthly">৳138.34</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Simple Interest Card -->
                        <div
                            class="bg-white dark:bg-gray-800 rounded-xl p-5 shadow-md border border-green-200 dark:border-green-900">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200">No Reinvestment</h3>
                                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                            </div>
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Final Amount</p>
                                    <p class="text-3xl font-bold text-green-600 dark:text-green-400"
                                        id="simpleFinalAmount">৳2,020.00</p>
                                </div>
                                <div
                                    class="flex justify-between items-center pt-3 border-t border-gray-100 dark:border-gray-700">
                                    <div>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Profit</p>
                                        <p class="text-xl font-semibold text-green-600 dark:text-green-400"
                                            id="simpleTotalProfit">৳1,020.00</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Monthly Profit</p>
                                        <p class="text-lg font-semibold text-green-600 dark:text-green-400"
                                            id="simpleMonthlyProfit">৳85.00</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Difference Card -->
                    <div
                        class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-gray-800 dark:to-gray-800 rounded-xl p-5 border border-yellow-200 dark:border-yellow-800">
                        <h3 class="font-semibold text-lg text-gray-800 dark:text-gray-200 mb-3">Compounding Advantage</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Extra Profit</p>
                                <p class="text-2xl font-bold text-red-600 dark:text-red-400" id="extraProfit">৳640.02</p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Performance Gap</p>
                                <p class="text-2xl font-bold text-purple-600 dark:text-purple-400"
                                    id="profitDifferencePercent">62.7%</p>
                            </div>
                            <div class="text-center">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Compounding Power</p>
                                <p class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="compoundingFactor">
                                    2.66x</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Detailed Results Grid -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Compound Interest Table -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-semibold text-xl text-gray-800 dark:text-gray-200 flex items-center">
                                <span class="w-3 h-3 rounded-full bg-blue-500 mr-2"></span>
                                Compound Interest Breakdown
                            </h3>
                            <span
                                class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                Profit Reinvested Each Month
                            </span>
                        </div>

                        <div class="bg-blue-50 dark:bg-blue-900/20 p-4 rounded-lg mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Initial Investment</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400"
                                        id="compoundInitialAmount">৳1,000.00</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Monthly Rate</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400"
                                        id="compoundMonthlyRate">8.5%</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Months</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400"
                                        id="compoundTotalMonths">12</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Final Amount</p>
                                    <p class="text-xl font-bold text-blue-600 dark:text-blue-400"
                                        id="compoundFinalAmountDetailed">৳2,660.02</p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <div class="overflow-y-auto max-h-96">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Month</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Starting Amount</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Monthly Profit</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Ending Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="compoundMonthlyDetails"
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <!-- Table rows will be populated by JavaScript -->
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold">
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">Total</td>
                                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">-</td>
                                            <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400"
                                                id="compoundTotalProfitDetailed">৳1,660.02</td>
                                            <td class="px-4 py-3 text-sm text-blue-600 dark:text-blue-400"
                                                id="compoundFinalTotal">৳2,660.02</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Simple Interest Table -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="font-semibold text-xl text-gray-800 dark:text-gray-200 flex items-center">
                                <span class="w-3 h-3 rounded-full bg-green-500 mr-2"></span>
                                Simple Interest Breakdown
                            </h3>
                            <span
                                class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                Same Profit Each Month
                            </span>
                        </div>

                        <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg mb-6">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Initial Investment</p>
                                    <p class="text-xl font-bold text-green-600 dark:text-green-400"
                                        id="simpleInitialAmount">৳1,000.00</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Monthly Profit</p>
                                    <p class="text-xl font-bold text-green-600 dark:text-green-400"
                                        id="simpleMonthlyProfitStatic">৳85.00</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Months</p>
                                    <p class="text-xl font-bold text-green-600 dark:text-green-400"
                                        id="simpleTotalMonths">12</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Final Amount</p>
                                    <p class="text-xl font-bold text-green-600 dark:text-green-400"
                                        id="simpleFinalAmountDetailed">৳2,020.00</p>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <div class="overflow-y-auto max-h-96">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Month</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Starting Amount</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Monthly Profit</th>
                                            <th
                                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Ending Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody id="simpleMonthlyDetails"
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <!-- Table rows will be populated by JavaScript -->
                                    </tbody>
                                    <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold">
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">Total</td>
                                            <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">-</td>
                                            <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400"
                                                id="simpleTotalProfitDetailed">৳1,020.00</td>
                                            <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400"
                                                id="simpleFinalTotal">৳2,020.00</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get form elements
            const form = document.getElementById('investmentCalculator');
            const monthsRange = document.getElementById('monthsRange');
            const monthsInput = document.getElementById('months');
            const percentageRange = document.getElementById('percentageRange');
            const percentageInput = document.getElementById('percentage');
            const amountInput = document.getElementById('amount');
            const quickPresets = document.querySelectorAll('[data-percentage]');

            // Link range sliders with number inputs
            monthsRange.addEventListener('input', function() {
                monthsInput.value = this.value;
                calculateInvestment();
            });

            monthsInput.addEventListener('input', function() {
                monthsRange.value = Math.min(this.value, 60);
                calculateInvestment();
            });

            percentageRange.addEventListener('input', function() {
                percentageInput.value = this.value;
                calculateInvestment();
            });

            percentageInput.addEventListener('input', function() {
                percentageRange.value = this.value;
                calculateInvestment();
            });

            // Add event listeners to all inputs
            form.addEventListener('input', function(e) {
                clearTimeout(this.timer);
                this.timer = setTimeout(calculateInvestment, 200);
            });

            // Quick preset buttons
            quickPresets.forEach(button => {
                button.addEventListener('click', function() {
                    percentageInput.value = this.getAttribute('data-percentage');
                    percentageRange.value = this.getAttribute('data-percentage');
                    calculateInvestment();
                });
            });

            // Initial calculation
            calculateInvestment();

            function calculateInvestment() {
                // Get input values
                const amount = parseFloat(amountInput.value) || 1000;
                const months = parseInt(monthsInput.value) || 12;
                const percentage = parseFloat(percentageInput.value) || 8.5;

                // Calculate monthly interest rate
                const monthlyRate = percentage / 100;

                // Compound Interest Calculation
                let compoundCurrentAmount = amount;
                let compoundTotalProfit = 0;
                let compoundMonthlyProfits = [];

                // Clear and prepare compound monthly details
                const compoundMonthlyDetails = document.getElementById('compoundMonthlyDetails');
                compoundMonthlyDetails.innerHTML = '';

                // Generate compound interest table
                for (let month = 1; month <= months; month++) {
                    const startingAmount = compoundCurrentAmount;
                    const profit = startingAmount * monthlyRate;
                    const endingAmount = startingAmount + profit;

                    compoundTotalProfit += profit;
                    compoundMonthlyProfits.push(profit);
                    compoundCurrentAmount = endingAmount;

                    // Create table row
                    const row = document.createElement('tr');
                    row.className = month % 2 === 0 ? 'bg-gray-50 dark:bg-gray-700/50' : '';
                    row.innerHTML = `
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">${month}</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">৳${startingAmount.toFixed(2)}</td>
                        <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">৳${profit.toFixed(2)}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-blue-600 dark:text-blue-400">৳${endingAmount.toFixed(2)}</td>
                    `;

                    compoundMonthlyDetails.appendChild(row);
                }

                // Simple Interest Calculation
                const simpleMonthlyProfit = amount * monthlyRate;
                const simpleTotalProfit = simpleMonthlyProfit * months;
                const simpleFinalAmount = amount + simpleTotalProfit;

                // Clear and prepare simple monthly details
                const simpleMonthlyDetails = document.getElementById('simpleMonthlyDetails');
                simpleMonthlyDetails.innerHTML = '';

                // Generate simple interest table
                for (let month = 1; month <= months; month++) {
                    const startingAmount = amount; // Always same starting amount
                    const profit = simpleMonthlyProfit; // Always same profit
                    const endingAmount = amount + (simpleMonthlyProfit * month);

                    // Create table row
                    const row = document.createElement('tr');
                    row.className = month % 2 === 0 ? 'bg-gray-50 dark:bg-gray-700/50' : '';
                    row.innerHTML = `
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">${month}</td>
                        <td class="px-4 py-3 text-sm text-gray-800 dark:text-gray-300">৳${startingAmount.toFixed(2)}</td>
                        <td class="px-4 py-3 text-sm text-green-600 dark:text-green-400">৳${profit.toFixed(2)}</td>
                        <td class="px-4 py-3 text-sm font-semibold text-green-600 dark:text-green-400">৳${endingAmount.toFixed(2)}</td>
                    `;

                    simpleMonthlyDetails.appendChild(row);
                }

                // Update all display elements
                updateDisplayElements(amount, months, percentage, compoundCurrentAmount, compoundTotalProfit,
                    simpleFinalAmount, simpleTotalProfit, simpleMonthlyProfit);
            }

            function updateDisplayElements(amount, months, percentage, compoundFinal, compoundTotalProfit,
                simpleFinal, simpleTotalProfit, simpleMonthlyProfit) {
                // Format numbers with commas
                const formatCurrency = (num) => {
                    return '৳' + num.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                };

                // Update compound interest displays
                document.getElementById('compoundInitialAmount').textContent = formatCurrency(amount);
                document.getElementById('compoundMonthlyRate').textContent = percentage + '%';
                document.getElementById('compoundFinalAmount').textContent = formatCurrency(compoundFinal);
                document.getElementById('compoundFinalAmountDetailed').textContent = formatCurrency(compoundFinal);
                document.getElementById('compoundTotalProfit').textContent = formatCurrency(compoundTotalProfit);
                document.getElementById('compoundTotalProfitDetailed').textContent = formatCurrency(
                    compoundTotalProfit);
                document.getElementById('compoundFinalTotal').textContent = formatCurrency(compoundFinal);
                document.getElementById('compoundTotalMonths').textContent = months;
                document.getElementById('compoundAvgMonthly').textContent = formatCurrency(compoundTotalProfit /
                    months);

                // Update simple interest displays
                document.getElementById('simpleInitialAmount').textContent = formatCurrency(amount);
                document.getElementById('simpleFinalAmount').textContent = formatCurrency(simpleFinal);
                document.getElementById('simpleFinalAmountDetailed').textContent = formatCurrency(simpleFinal);
                document.getElementById('simpleTotalProfit').textContent = formatCurrency(simpleTotalProfit);
                document.getElementById('simpleTotalProfitDetailed').textContent = formatCurrency(
                simpleTotalProfit);
                document.getElementById('simpleFinalTotal').textContent = formatCurrency(simpleFinal);
                document.getElementById('simpleTotalMonths').textContent = months;
                document.getElementById('simpleMonthlyProfit').textContent = formatCurrency(simpleMonthlyProfit);
                document.getElementById('simpleMonthlyProfitStatic').textContent = formatCurrency(
                    simpleMonthlyProfit);

                // Update comparison
                const extraProfit = compoundFinal - simpleFinal;
                const profitDifferencePercent = ((extraProfit / simpleTotalProfit) * 100).toFixed(1);
                const compoundingFactor = (compoundFinal / simpleFinal).toFixed(2);

                document.getElementById('extraProfit').textContent = formatCurrency(extraProfit);
                document.getElementById('profitDifferencePercent').textContent = profitDifferencePercent + '%';
                document.getElementById('compoundingFactor').textContent = compoundingFactor + 'x';
            }

            // Format currency inputs on blur
            amountInput.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                    calculateInvestment();
                }
            });
        });
    </script>
@endpush
