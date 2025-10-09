@push('scripts')
    <script>
        const engineData = @json($engines->keyBy('id'));
        let exchangeRate = 57.5; // fallback

        const PLATFORM_COST = 5.00;

        const priceInput = document.querySelector('input[name="monthly_price"]');
        const maxTokensInput = document.querySelector('input[name="max_tokens"]');
        const msgLimitInput = document.querySelector('input[name="daily_message_limit"]');
        const engineSelect = document.querySelector('select[name="engine_id"]');

        const estRevenue = document.getElementById('est_revenue');
        const estMsgs = document.getElementById('est_msgs');
        const estTokens = document.getElementById('est_tokens');
        const estCost = document.getElementById('est_cost');
        const estCostPerMsg = document.getElementById('est_cost_per_msg');
        const estProfit = document.getElementById('est_profit');
        const warningBox = document.getElementById('warningBox');
        const recommendationBox = document.getElementById('planRecommendation');

        async function fetchExchangeRate() {
            try {
                const response = await fetch('https://open.er-api.com/v6/latest/USD');
                const data = await response.json();
                if (data && data.rates && data.rates.ETB) {
                    exchangeRate = data.rates.ETB;
                }
            } catch (e) {
                console.warn('⚠️ Failed to fetch ETB rate. Using fallback:', exchangeRate);
            } finally {
                calculateEstimation();
            }
        }

        function calculateEstimation() {
            const recommendedPriceEl = document.getElementById('recommended_price');
            const monthlyPrice = parseFloat(priceInput?.value || 0);
            const maxTokens = parseInt(maxTokensInput?.value || 0);
            const dailyLimit = parseInt(msgLimitInput?.value || 0);
            const engineId = engineSelect?.value;

            if (!monthlyPrice || !maxTokens || !dailyLimit || !engineId || !engineData[engineId]) {
                return;
            }

            const engine = engineData[engineId];
            const pricePer1K = parseFloat(engine.price_per_1k || 0);

            const messagesPerMonth = dailyLimit * 30;
            const tokensPerMsg = Math.floor(maxTokens / dailyLimit);
            const totalTokens = messagesPerMonth * tokensPerMsg;

            const costUSD = (totalTokens / 1000) * pricePer1K;
            const costETB = costUSD * exchangeRate;
            const costPerMessageETB = costETB / messagesPerMonth;
            const profit = monthlyPrice - costETB - PLATFORM_COST;

            const baseCostETB = costETB + PLATFORM_COST;
            const recommendedPrice = baseCostETB * 1.1; // ➕10% profit target

            recommendedPriceEl.textContent = recommendedPrice.toFixed(2);
            estRevenue.textContent = monthlyPrice.toFixed(2);
            estMsgs.textContent = messagesPerMonth;
            estTokens.textContent = totalTokens;
            estCost.textContent = costETB.toFixed(2);
            estCostPerMsg.textContent = costPerMessageETB.toFixed(4);
            estProfit.textContent = profit.toFixed(2);
            warningBox.style.display = profit < 0 ? 'block' : 'none';

            // 💡 Recommendation logic
            let message = '';
            if (profit < 0) {
                message = '❌ This plan is not profitable. Consider increasing the monthly price or reducing token limits.';
                recommendationBox.className = 'alert alert-danger p-2 m-0';
            } else if (profit < 10) {
                message = '⚠️ This plan is profitable but with a small margin. Review platform costs or limit tokens.';
                recommendationBox.className = 'alert alert-warning p-2 m-0';
            } else if (profit >= 10 && profit < 30) {
                message = '✅ This plan is reasonably profitable. Consider offering it as your standard package.';
                recommendationBox.className = 'alert alert-success p-2 m-0';
            } else {
                message = '💰 High profit margin! You may have room to offer better features or discounts.';
                recommendationBox.className = 'alert alert-primary p-2 m-0';
            }

            recommendationBox.textContent = message;
        }

        [priceInput, maxTokensInput, msgLimitInput, engineSelect].forEach(input =>
            input?.addEventListener('input', calculateEstimation)
        );

        window.addEventListener('DOMContentLoaded', fetchExchangeRate);

        document.addEventListener('DOMContentLoaded', function() {
            const isDefaultCheckbox = document.getElementById('is_default');
            const priceInput = document.querySelector('input[name="monthly_price"]');

            if (isDefaultCheckbox && priceInput) {
                isDefaultCheckbox.addEventListener('change', function() {
                    const price = parseFloat(priceInput.value);
                    if (this.checked && price > 0) {
                        alert(
                            '⚠️ You are marking a paid plan as the default free plan.\n\nUsers without subscriptions will receive this plan for free.'
                            );
                    }
                });
            }
        });
    </script>
@endpush
