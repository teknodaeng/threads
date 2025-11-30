document.addEventListener('DOMContentLoaded', function() {
    if (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN) {
        // Post thread baru (parent_id = null)
        const btnPost = document.getElementById('btnPost');
        const newContent = document.getElementById('threadContent');
        const charCount = document.getElementById('charCount');
        const imageInput = document.getElementById('threadImages');
        const imagePreview = document.getElementById('imagePreview');

        if (btnPost && newContent && charCount) {
            // Update char count on input
            newContent.addEventListener('input', function() {
                const len = this.value.length;
                charCount.innerText = len + '/280';
                
                if (len > 280) {
                    charCount.classList.add('text-red-500');
                    btnPost.disabled = true;
                } else {
                    charCount.classList.remove('text-red-500');
                    btnPost.disabled = false;
                }
            });

            let selectedFiles = [];

            if (imageInput && imagePreview) {
                imageInput.addEventListener('change', function () {
                    const files = Array.from(this.files);
                    if (selectedFiles.length + files.length > 10) {
                        alert('Maksimal 10 gambar');
                        this.value = ''; 
                        return;
                    }

                    files.forEach(file => {
                        if (file.type.startsWith('image/')) {
                            selectedFiles.push(file);
                        }
                    });

                    renderPreviews();
                    this.value = ''; // Reset input to allow selecting more
                });
            }

            function renderPreviews() {
                imagePreview.innerHTML = '';
                selectedFiles.forEach((file, index) => {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        const imgDiv = document.createElement('div');
                        imgDiv.className = 'relative flex-shrink-0 w-48 h-48 group';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'w-full h-full object-cover rounded-xl border border-gray-200 shadow-sm';
                        
                        const removeBtn = document.createElement('button');
                        removeBtn.innerHTML = '&times;';
                        removeBtn.className = 'absolute top-2 right-2 bg-gray-800 bg-opacity-50 text-white rounded-full w-6 h-6 flex items-center justify-center hover:bg-opacity-75 transition duration-200';
                        removeBtn.onclick = function() {
                            selectedFiles.splice(index, 1);
                            renderPreviews();
                        };

                        imgDiv.appendChild(img);
                        imgDiv.appendChild(removeBtn);
                        imagePreview.appendChild(imgDiv);
                    }
                    reader.readAsDataURL(file);
                });
            }

            btnPost.addEventListener('click', function () {
                const content = newContent.value.trim();
                
                if (!content && selectedFiles.length === 0) return alert('Isi konten atau upload gambar dulu 😁');
                if (content.length > 280) return alert('Kepanjangan bro, maks 280 karakter');
                if (selectedFiles.length > 10) return alert('Maksimal 10 gambar');

                const csrfToken = document.getElementById('csrfToken').value;
                const formData = new FormData();
                formData.append('content', content);
                formData.append('csrf_token', csrfToken);
                
                selectedFiles.forEach(file => {
                    formData.append('images[]', file);
                });

                fetch('actions/post_thread.php', {
                    method: 'POST',
                    body: formData
                })
                    .then(r => r.json())
                    .then(res => {
                        if (res.success) {
                            location.reload();
                        } else {
                            alert(res.message || 'Gagal posting');
                        }
                    });
            });

            // Setup Autocomplete for Main Thread
            setupAutocomplete(newContent);
        }
    }

    // Load More
    const btnLoadMore = document.getElementById('btnLoadMore');
    if (btnLoadMore) {
        btnLoadMore.addEventListener('click', function () {
            const offset = parseInt(this.getAttribute('data-offset'), 10);
            const urlParams = new URLSearchParams(window.location.search);
            const tab = urlParams.get('tab') || 'foryou';

            fetch('actions/get_threads.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'offset=' + encodeURIComponent(offset) + '&tab=' + encodeURIComponent(tab)
            })
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        document.getElementById('loadMoreStatus').innerText =
                            res.message || 'Gagal load lebih banyak';
                        return;
                    }

                    // sisipkan HTML baru
                    const container = document.getElementById('threadsContainer');
                    const temp = document.createElement('div');
                    temp.innerHTML = res.html;
                    // pindahkan childnya ke container
                    while (temp.firstChild) {
                        container.appendChild(temp.firstChild);
                    }

                    // update offset
                    btnLoadMore.setAttribute('data-offset', res.nextOffset);

                    // kalau sudah tidak ada lagi, sembunyikan tombol
                    if (!res.hasMore) {
                        btnLoadMore.style.display = 'none';
                        document.getElementById('loadMoreStatus').innerText = 'Sudah habis, tidak ada thread lain.';
                    }

                    // re-bind event untuk tombol Like & Reply yang baru
                    rebindThreadEvents();
                    checkTruncatedContent();
                });
        });
    }

    // panggil sekali di awal
    rebindThreadEvents();
});

// Fungsi untuk bind event Like & Reply
function rebindThreadEvents() {
    if (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN) {
        // Like
        document.querySelectorAll('.btnLike').forEach(btn => {
            if (btn.dataset.boundLike) return; // biar tidak dobel
            btn.dataset.boundLike = '1';

            btn.addEventListener('click', function () {
                const threadDiv = this.closest('.thread');
                const threadId = threadDiv.getAttribute('data-id');
                const likeCountSpan = this.querySelector('.likeCount');
                const svgIcon = this.querySelector('.icon-like');
                const csrfToken = document.getElementById('csrfToken').value;

                // Optimistic Update
                const isLiked = svgIcon.classList.contains('fill-current');
                const intendedState = !isLiked;
                let currentCount = parseInt(likeCountSpan.textContent) || 0;

                if (intendedState) {
                    // Like
                    svgIcon.classList.add('fill-current');
                    this.classList.add('text-red-500');
                    this.classList.remove('text-gray-500');
                    likeCountSpan.textContent = currentCount + 1;
                } else {
                    // Unlike
                    svgIcon.classList.remove('fill-current');
                    this.classList.remove('text-red-500');
                    this.classList.add('text-gray-500');
                    likeCountSpan.textContent = Math.max(0, currentCount - 1);
                }

                fetch('actions/like_thread.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'thread_id=' + encodeURIComponent(threadId) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) {
                            // Revert
                            alert(res.message || 'Gagal like');
                            if (intendedState) {
                                svgIcon.classList.remove('fill-current');
                                this.classList.remove('text-red-500');
                                this.classList.add('text-gray-500');
                                likeCountSpan.textContent = currentCount;
                            } else {
                                svgIcon.classList.add('fill-current');
                                this.classList.add('text-red-500');
                                this.classList.remove('text-gray-500');
                                likeCountSpan.textContent = currentCount;
                            }
                        } else {
                            // Sync count
                            likeCountSpan.textContent = res.count;
                        }
                    })
                    .catch(() => {
                        // Revert on error
                        if (intendedState) {
                            svgIcon.classList.remove('fill-current');
                            this.classList.remove('text-red-500');
                            this.classList.add('text-gray-500');
                            likeCountSpan.textContent = currentCount;
                        } else {
                            svgIcon.classList.add('fill-current');
                            this.classList.add('text-red-500');
                            this.classList.remove('text-gray-500');
                            likeCountSpan.textContent = currentCount;
                        }
                    });
            });
        });

        // Submit Reply
        document.querySelectorAll('.btnReply').forEach(btn => {
            if (btn.dataset.boundReply) return;
            btn.dataset.boundReply = '1';

            btn.addEventListener('click', function () {
                const threadDiv = this.closest('.thread');
                const threadId = threadDiv.getAttribute('data-id');
                const textarea = threadDiv.querySelector('.replyContent');
                const content = textarea.value.trim();
                if (!content) return alert('Isi reply dulu');

                const csrfToken = document.getElementById('csrfToken').value;

                fetch('actions/post_thread.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'content=' + encodeURIComponent(content) +
                        '&parent_id=' + encodeURIComponent(threadId) +
                        '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                    .then(r => r.json())
                    .then(res => {
                        if (!res.success) return alert(res.message || 'Gagal reply');
                        location.reload();
                    });
            });
        });

        // Tag/Focus Reply Button (New)
        document.querySelectorAll('.btnTagReply').forEach(btn => {
            if (btn.dataset.boundTag) return;
            btn.dataset.boundTag = '1';

            btn.addEventListener('click', function () {
                const threadDiv = this.closest('.thread');
                const username = threadDiv.getAttribute('data-username');
                const textarea = threadDiv.querySelector('.replyContent');

                if (textarea) {
                    // Show the form if it's hidden
                    textarea.parentElement.classList.remove('hidden');
                    
                    if (username && !textarea.value.includes('@' + username)) {
                        textarea.value = '@' + username + ' ' + textarea.value;
                    }
                    textarea.focus();
                }
            });
        });

        // Setup Autocomplete for Reply Textareas
        document.querySelectorAll('.replyContent').forEach(el => setupAutocomplete(el));
    }
}

// Global function for carousel navigation
window.scrollCarousel = function(id, direction) {
    const container = document.getElementById(id);
    if (container) {
        const scrollAmount = container.clientWidth;
        container.scrollBy({
            left: direction * scrollAmount,
            behavior: 'smooth'
        });
    }
};
// Check for truncated content
function checkTruncatedContent() {
    document.querySelectorAll('.thread-content').forEach(el => {
        if (el.scrollHeight > el.clientHeight) {
            const btn = el.nextElementSibling;
            if (btn && btn.classList.contains('btnReadMore')) {
                btn.classList.remove('hidden');
            }
        }
    });
}

// Call initially and after load more
document.addEventListener('DOMContentLoaded', checkTruncatedContent);
// Also export it to be called from get_threads
window.checkTruncatedContent = checkTruncatedContent;

// Follow Button Logic
const btnFollow = document.getElementById('btnFollow');
if (btnFollow) {
    btnFollow.addEventListener('click', function() {
        const userId = this.getAttribute('data-id');
        const csrfToken = document.getElementById('csrfToken').value;
        
        fetch('actions/follow_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'user_id=' + encodeURIComponent(userId) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(r => r.json())
        .then(res => {
            if (!res.success) return alert(res.message || 'Gagal follow');
            
            const followerCountSpan = document.getElementById('followerCount');
            if (res.status === 'followed') {
                this.textContent = 'Following';
                this.classList.remove('bg-black', 'text-white', 'hover:bg-gray-800');
                this.classList.add('bg-white', 'border', 'border-gray-300', 'text-gray-900', 'hover:bg-gray-50');
                if (followerCountSpan) followerCountSpan.textContent = res.follower_count;
            } else {
                this.textContent = 'Follow';
                this.classList.remove('bg-white', 'border', 'border-gray-300', 'text-gray-900', 'hover:bg-gray-50');
                this.classList.add('bg-black', 'text-white', 'hover:bg-gray-800');
                if (followerCountSpan) followerCountSpan.textContent = res.follower_count;
            }
        });
    });

}

// Autocomplete Logic
function setupAutocomplete(textarea) {
    if (textarea.dataset.autocompleteBound) return;
    textarea.dataset.autocompleteBound = '1';

    let dropdown = null;

    textarea.addEventListener('input', function(e) {
        const cursorPosition = this.selectionStart;
        const textBeforeCursor = this.value.substring(0, cursorPosition);
        const words = textBeforeCursor.split(/\s/);
        const currentWord = words[words.length - 1];

        if (currentWord.startsWith('@') && currentWord.length > 1) {
            const query = currentWord.substring(1);
            fetchUsers(query, this, cursorPosition);
        } else {
            closeDropdown();
        }
    });

    textarea.addEventListener('blur', function() {
        // Delay closing to allow click event on dropdown
        setTimeout(closeDropdown, 200);
    });

    function fetchUsers(query, input, cursorPosition) {
        fetch('actions/search_users.php?q=' + encodeURIComponent(query))
            .then(r => r.json())
            .then(users => {
                if (users.length > 0) {
                    showDropdown(users, input, cursorPosition, query);
                } else {
                    closeDropdown();
                }
            });
    }

    function showDropdown(users, input, cursorPosition, query) {
        closeDropdown();

        dropdown = document.createElement('ul');
        dropdown.className = 'absolute bg-white border border-gray-200 shadow-lg rounded-md z-50 w-64 max-h-48 overflow-y-auto';
        
        // Position logic (simplified)
        const rect = input.getBoundingClientRect();
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.top = (rect.bottom + window.scrollY) + 'px';

        users.forEach(user => {
            const li = document.createElement('li');
            li.className = 'flex items-center p-2 hover:bg-gray-100 cursor-pointer border-b border-gray-100 last:border-0';
            
            const img = document.createElement('img');
            img.src = user.image_path || 'assets/default-avatar.png';
            img.className = 'w-8 h-8 rounded-full mr-2 object-cover';
            
            const div = document.createElement('div');
            const name = document.createElement('div');
            name.className = 'font-bold text-sm text-gray-800';
            name.textContent = user.fullname || user.username;
            
            const username = document.createElement('div');
            username.className = 'text-xs text-gray-500';
            username.textContent = '@' + user.username;

            div.appendChild(name);
            div.appendChild(username);
            li.appendChild(img);
            li.appendChild(div);

            li.addEventListener('click', function() {
                const text = input.value;
                const before = text.substring(0, cursorPosition - query.length - 1);
                const after = text.substring(cursorPosition);
                const newText = before + '@' + user.username + ' ' + after;
                
                input.value = newText;
                input.focus();
                closeDropdown();
            });

            dropdown.appendChild(li);
        });

        document.body.appendChild(dropdown);
    }

    function closeDropdown() {
        if (dropdown) {
            dropdown.remove();
            dropdown = null;
        }
    }
}
