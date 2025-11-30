<?php
// search.php
session_start();
require_once 'core/db.php';
require_once 'core/helpers.php';

$is_logged_in = isset($_SESSION['user_id']);
$page_title = 'Cari - Mini Threads';
require_once 'includes/header.php';
?>

<div class="max-w-2xl mx-auto p-4">
    <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
        <h2 class="text-xl font-bold text-gray-800 mb-4">Cari</h2>
        <div class="relative">
            <input type="text" id="searchInput" placeholder="Cari user atau thread..."
                class="w-full p-3 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 pl-10">
            <svg class="w-5 h-5 text-gray-400 absolute left-3 top-3.5" fill="none" stroke="currentColor"
                viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
        </div>
    </div>

    <div id="searchResults" class="space-y-6">
        <!-- Results will appear here -->
        <div id="usersResult" class="space-y-2"></div>
        <div id="threadsResult" class="space-y-4"></div>
    </div>

    <input type="hidden" id="csrfToken" value="<?= e(csrf_token()) ?>">
    <script>
        const searchInput = document.getElementById('searchInput');
        const usersResult = document.getElementById('usersResult');
        const threadsResult = document.getElementById('threadsResult');
        let timeout = null;

        searchInput.addEventListener('input', function () {
            clearTimeout(timeout);
            const q = this.value.trim();

            if (q.length < 2) {
                usersResult.innerHTML = '';
                threadsResult.innerHTML = '';
                return;
            }

            timeout = setTimeout(() => {
                fetch('actions/search.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            // Render Users
                            let usersHtml = '';
                            if (res.users.length > 0) {
                                usersHtml += '<h3 class="text-lg font-bold text-gray-800 mb-2">Users</h3>';
                                res.users.forEach(u => {
                                    usersHtml += `<div class="bg-white p-3 rounded-lg shadow-sm border border-gray-200 hover:bg-gray-50 transition duration-150">
                                        <a href="profile.php?u=${u.username}" class="flex items-center space-x-3">
                                            <img src="${u.user_image || 'assets/default-avatar.png'}" alt="Profile Picture" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                                            <div>
                                                <div class="font-bold text-gray-900 hover:text-blue-600">${u.fullname}</div>
                                                <div class="text-gray-500 font-normal text-sm">@${u.username}</div>
                                            </div>
                                        </a>
                                    </div>`;
                                });
                            }
                            usersResult.innerHTML = usersHtml;

                            // Render Threads
                            let threadsHtml = '';
                            if (res.threads.length > 0) {
                                threadsHtml += '<h3 class="text-lg font-bold text-gray-800 mb-2">Threads</h3>';
                                res.threads.forEach(t => {
                                    const is_liked = t.is_liked > 0; // Assuming API returns this
                                    const like_text = is_liked ? 'Unlike' : 'Like';
                                    const like_class = is_liked ? 'text-red-500' : 'text-gray-500 hover:text-red-500';

                                    threadsHtml += `
                                        <div class="thread bg-white p-4 rounded-lg shadow-sm border border-gray-200" data-id="${t.id}" data-username="${t.username}">
                                            <div class="flex items-center mb-2 space-x-2">
                                                <img src="${t.user_image || 'assets/default-avatar.png'}" alt="${t.username}" class="w-10 h-10 rounded-full object-cover border border-gray-200">
                                                <div>
                                                    <strong class="text-gray-900 mr-1">${t.fullname}</strong>
                                                    <a href="profile.php?u=${t.username}" class="text-gray-500 text-sm hover:text-blue-500">@${t.username}</a> 
                                                </div>
                                                <span class="text-gray-400 mx-2">•</span>
                                                <a href="thread.php?id=${t.id}" class="text-gray-500 text-xs hover:underline">${t.created_at}</a>
                                            </div>
                                            <div class="text-gray-800 mb-2 leading-relaxed">${t.content}</div>
                                            ${(() => {
                                            let imgs = [];
                                            if (t.images) {
                                                imgs = t.images.split(',');
                                            } else if (t.image_path) {
                                                imgs = [t.image_path];
                                            }
                                            if (imgs.length > 0) {
                                                let html = `<div class="relative mb-3 group">
                                                                <div id="carousel-search-${t.id}" class="flex overflow-x-auto snap-x snap-mandatory gap-2 pb-2 scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">`;
                                                imgs.forEach(img => {
                                                    let widthClass = imgs.length > 1 ? 'w-5/6' : 'w-full';
                                                    html += `<div class="snap-center flex-shrink-0 ${widthClass}">
                                                                <img src="${img}" alt="Thread Image" class="rounded-lg w-full h-48 object-cover border border-gray-200">
                                                             </div>`;
                                                });
                                                html += `</div>`;
                                                if (imgs.length > 1) {
                                                    html += `<button onclick="scrollCarousel('carousel-search-${t.id}', -1)" class="absolute left-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                                                             </button>
                                                             <button onclick="scrollCarousel('carousel-search-${t.id}', 1)" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-black bg-opacity-50 text-white p-1 rounded-full opacity-0 group-hover:opacity-100 transition hover:bg-opacity-75">
                                                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                             </button>`;
                                                }
                                                html += `</div>`;
                                                return html;
                                            }
                                            return '';
                                        })()}
                                            
                                            <div class="flex items-center space-x-4">
                                                <button class="btnLike flex items-center space-x-1 ${like_class} transition duration-200 group">
                                                    <svg class="w-5 h-5 icon-like ${is_liked ? 'fill-current' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                                    </svg>
                                                    <span class="text-xs bg-gray-100 px-2 py-1 rounded-full likeCount">${t.like_count}</span>
                                                </button>
                                                <button class="btnTagReply text-gray-500 hover:text-blue-500 font-medium transition duration-200 flex items-center space-x-1">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                                                    </svg>
                                                    ${t.reply_count > 0 ? `<span class="text-xs bg-gray-100 px-2 py-1 rounded-full">${t.reply_count}</span>` : ''}
                                                </button>
                                            </div>

                                            <div class="mt-4 hidden">
                                                <textarea class="replyContent w-full p-2 border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 text-sm" rows="2" placeholder="Balas..."></textarea>
                                                <div class="mt-2 text-right">
                                                    <button class="btnReply bg-blue-500 text-white px-3 py-1 rounded-md text-sm font-bold hover:bg-blue-600 transition duration-200">Reply</button>
                                                </div>
                                            </div>
                                        </div>
                                    `;
                                });
                            }
                            threadsResult.innerHTML = threadsHtml;

                            if (res.users.length === 0 && res.threads.length === 0) {
                                threadsResult.innerHTML = '<p class="text-gray-500 text-center py-4">Tidak ditemukan hasil.</p>';
                            }

                            // Re-attach event listeners for new elements
                            if (window.attachThreadListeners) {
                                window.attachThreadListeners();
                            }
                        }
                    });
            }, 300); // Debounce 300ms
        });
    </script>
    <script src="assets/app.js"></script>
    <script src="assets/image-modal.js"></script>
</div>

<?php require_once 'includes/footer.php'; ?>