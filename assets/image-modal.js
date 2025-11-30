// Image Modal JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Create modal HTML if not exists
    if (!document.getElementById('imageModal')) {
        const modalHTML = `
            <div id="imageModal" class="hidden fixed inset-0 bg-black bg-opacity-90 z-50 p-4" style="display: none; align-items: center; justify-content: center;" onclick="closeImageModal(event)">
                <div class="relative max-w-7xl max-h-full">
                    <button onclick="closeImageModal(event)" class="absolute top-4 right-4 text-white hover:text-gray-300 z-10">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                    <img id="modalImage" src="" alt="Full size image" class="max-w-full max-h-screen object-contain rounded-lg">
                </div>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    // Add click handlers to all thread images
    attachImageClickHandlers();
});

function attachImageClickHandlers() {
    // Select all images inside thread image containers
    const threadImages = document.querySelectorAll('.thread img[src*="uploads/"], .thread-content img[src*="uploads/"]');
    
    threadImages.forEach(img => {
        img.style.cursor = 'pointer';
        img.onclick = function(e) {
            e.preventDefault();
            e.stopPropagation();
            openImageModal(this.src);
        };
    });
}

function openImageModal(imageSrc) {
    const modal = document.getElementById('imageModal');
    const modalImage = document.getElementById('modalImage');
    
    if (modal && modalImage) {
        modalImage.src = imageSrc;
        modal.style.display = 'flex';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevent background scrolling
    }
}

function closeImageModal(event) {
    // Only close if clicking the background or close button
    if (event.target.id === 'imageModal' || event.target.closest('button')) {
        const modal = document.getElementById('imageModal');
        if (modal) {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.style.overflow = ''; // Restore scrolling
        }
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('imageModal');
        if (modal && modal.style.display === 'flex') {
            modal.style.display = 'none';
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    }
});

// Export function for dynamically loaded content
window.attachImageClickHandlers = attachImageClickHandlers;
window.openImageModal = openImageModal;
window.closeImageModal = closeImageModal;
