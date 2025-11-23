@extends('layouts.admin')
@section('title', 'Add AI Engine')

@section('content')
    <h2 class="mb-3">Add AI Engine</h2>
    @include('admin.ai_engines._form', ['ai_engine' => null])
@endsection

@push('scripts')
    <script>
        async function fetchExchangeRate() {
            try {
                const response = await fetch('https://open.er-api.com/v6/latest/USD');
                const data = await response.json();
                const etbRate = data.rates.ETB ?? 57.5;

                document.getElementById('usd-etb-rate').innerText = `${etbRate.toFixed(2)} ETB`;

                document.querySelectorAll('.price-local').forEach(el => {
                    const usd = parseFloat(el.getAttribute('data-usd'));
                    el.innerText = (usd * etbRate).toFixed(4);
                });

                updateCostEstimate(etbRate);
                bindEngineSelector(etbRate);
            } catch (err) {
                console.warn('Exchange fetch failed:', err);
                document.getElementById('usd-etb-rate').innerText = '57.50 ETB (static)';

                document.querySelectorAll('.price-local').forEach(el => {
                    const usd = parseFloat(el.getAttribute('data-usd'));
                    el.innerText = (usd * 57.5).toFixed(4);
                });

                updateCostEstimate(57.5);
                bindEngineSelector(57.5);
            }
        }

        function updateCostEstimate(rateETB) {
            const tokenInput = document.querySelector('input[name="max_tokens"]');
            const priceInput = document.querySelector('input[name="price_per_1k"]');

            const calculate = () => {
                const tokens = parseFloat(tokenInput.value || 0);
                const pricePerK = parseFloat(priceInput.value || 0);
                const usdCost = (tokens / 1000) * pricePerK;
                const etbCost = usdCost * rateETB;

                document.getElementById('usd-cost').innerText = usdCost.toFixed(4);
                document.getElementById('etb-cost').innerText = etbCost.toFixed(2);
            };

            tokenInput.addEventListener('input', calculate);
            priceInput.addEventListener('input', calculate);
            calculate();
        }

        function bindEngineSelector(rateETB) {
            const buttons = document.querySelectorAll('.engine-option');

            buttons.forEach(btn => {
                btn.addEventListener('click', () => {
                    // Remove active highlight from others
                    buttons.forEach(b => b.classList.remove('active', 'bg-light', 'bg-warning'));
                    btn.classList.add('active', 'bg-warning');

                    // Autofill form fields
                    document.querySelector('input[name="name"]').value = btn.dataset.name;
                    document.querySelector('input[name="provider"]').value = btn.dataset.provider;
                    document.querySelector('input[name="api_url"]').value = btn.dataset.api;
                    document.querySelector('input[name="price_per_1k"]').value = btn.dataset.price;
                    document.querySelector('input[name="max_tokens"]').value = btn.dataset.tokens;
                    document.querySelector('input[name="model_type"]').value = btn.dataset.model_type;
                    document.querySelector('input[name="version"]').value = btn.dataset.version;

                    updateCostEstimate(rateETB);
                });
            });
        }

        fetchExchangeRate();

        function toggleAPIKeyVisibility(button) {
            const input = document.getElementById('apiKeyField');
            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            button.innerHTML = isHidden ? '<i class="fas fa-eye-slash"></i>' : '<i class="fas fa-eye"></i>';
        }
    </script>
@endpush
