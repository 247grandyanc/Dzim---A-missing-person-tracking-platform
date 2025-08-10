<footer class="bg-gray-800 border-t border-gray-700 mt-12">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
            <!-- Logo and About -->
            <div class="space-y-4">
                <div class="flex items-center">
                    <svg class="h-8 w-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span class="ml-2 text-xl font-bold text-white">DZIM<span class="text-blue-400">-GH</span></span>
                </div>
                <p class="text-gray-400 text-sm">
                    Ghana's premier people search platform with advanced biometric matching technology.
                </p>
                <div class="flex space-x-4">
                    <a href="#" class="text-gray-400 hover:text-blue-400">
                        <span class="sr-only">Facebook</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path fill-rule="evenodd" d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z" clip-rule="evenodd" />
                        </svg>
                    </a>
                    <a href="#" class="text-gray-400 hover:text-blue-400">
                        <span class="sr-only">Twitter</span>
                        <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.713v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                        </svg>
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-300 tracking-wider uppercase">Quick Links</h3>
                <ul class="space-y-2">
                    <li><a href="/search.php" class="text-gray-400 hover:text-blue-400 text-sm">Advanced Search</a></li>
                    <li><a href="/subscriptions.php" class="text-gray-400 hover:text-blue-400 text-sm">Subscription Plans</a></li>
                    <li><a href="/history.php" class="text-gray-400 hover:text-blue-400 text-sm">Search History</a></li>
                    <li><a href="/templates/missing_list.php" class="text-gray-400 hover:text-blue-400 text-sm">Missing Person</a></li>
                    <li><a href="/faq.php" class="text-gray-400 hover:text-blue-400 text-sm">FAQ</a></li>
                </ul>
            </div>

            <!-- Legal -->
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-300 tracking-wider uppercase">Legal</h3>
                <ul class="space-y-2">
                    <li><a href="/privacy.php" class="text-gray-400 hover:text-blue-400 text-sm">Privacy Policy</a></li>
                    <li><a href="/terms.php" class="text-gray-400 hover:text-blue-400 text-sm">Terms of Service</a></li>
                    <li><a href="/acceptable-use.php" class="text-gray-400 hover:text-blue-400 text-sm">Acceptable Use</a></li>
                    <li><a href="/gdpr.php" class="text-gray-400 hover:text-blue-400 text-sm">GDPR Compliance</a></li>
                </ul>
            </div>

            <!-- Newsletter -->
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-300 tracking-wider uppercase">Stay Updated</h3>
                <p class="text-gray-400 text-sm">Subscribe to our newsletter for the latest features and updates.</p>
                <form class="mt-4 sm:flex sm:max-w-md">
                    <label for="email" class="sr-only">Email address</label>
                    <input type="email" name="email" id="email" required class="appearance-none min-w-0 w-full bg-gray-700 border border-gray-600 rounded-md py-2 px-4 text-base text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Enter your email">
                    <div class="mt-3 rounded-md sm:mt-0 sm:ml-3 sm:flex-shrink-0">
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 flex items-center justify-center border border-transparent rounded-md py-2 px-4 text-base font-medium text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                            Subscribe
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Copyright -->
        <div class="mt-12 border-t border-gray-700 pt-8 flex flex-col md:flex-row justify-between items-center">
            <p class="text-gray-400 text-sm text-center md:text-left">
                &copy; <?= date('Y') ?> SEACH-GH. 247Grand Yanc Nexus - All rights reserved.
            </p>
            <div class="mt-4 md:mt-0 flex space-x-6">
                <a href="/sitemap.php" class="text-gray-400 hover:text-blue-400 text-sm">Sitemap</a>
                <a href="/contact.php" class="text-gray-400 hover:text-blue-400 text-sm">Contact Us</a>
                <a href="/report.php" class="text-gray-400 hover:text-blue-400 text-sm">Report Abuse</a>
            </div>
        </div>
    </div>
</footer>