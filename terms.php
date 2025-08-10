<?php require_once __DIR__ . '/includes/header.php'; ?>

<main class="container mx-auto px-4 py-12 max-w-4xl">
    <div class="bg-gray-800 rounded-xl p-6 md:p-8">
        <h1 class="text-3xl font-bold mb-8 text-center dark:text-white">Terms of Service</h1>
        <div class="prose dark:prose-invert max-w-none">
            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">1. Acceptance of Terms</h2>
                <p>
                    By accessing or using our services, you agree to be bound by these Terms. If you disagree with any part, you may not access the service.
                </p>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">2. Service Description</h2>
                <p class="mb-4">
                    Our platform provides person search capabilities with different tiers of service:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li><strong>Free Tier:</strong> Basic name/phone searches with limited results</li>
                    <li><strong>Paid Tier:</strong> Advanced search capabilities including biometric matching</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">3. User Responsibilities</h2>
                <p class="mb-4">
                    You agree to:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Use the service only for lawful purposes</li>
                    <li>Not harass, threaten or impersonate others</li>
                    <li>Not use automated systems to access our service</li>
                    <li>Maintain the confidentiality of your account</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">4. Prohibited Uses</h2>
                <p class="mb-4">
                    You may not use our service to:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Violate any laws or regulations</li>
                    <li>Stalk or harass individuals</li>
                    <li>Discriminate against protected groups</li>
                    <li>Conduct illegal background checks</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">5. Subscription Terms</h2>
                <p class="mb-4">
                    Paid subscriptions:
                </p>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Are billed in advance and non-refundable</li>
                    <li>Auto-renew unless canceled</li>
                    <li>Require valid payment information</li>
                </ul>
            </section>

            <section class="mb-8">
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">6. Limitation of Liability</h2>
                <p>
                    We are not liable for any indirect, incidental, or consequential damages arising from use of our service.
                </p>
            </section>

            <section>
                <h2 class="text-2xl font-semibold mb-4 dark:text-white">7. Changes to Terms</h2>
                <p>
                    We may modify these terms at any time. Continued use after changes constitutes acceptance.
                </p>
                <p class="mt-4 text-sm text-gray-400">
                    Last Updated: <?= date('F j, Y') ?>
                </p>
            </section>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>