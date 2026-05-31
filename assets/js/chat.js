// Chat application for Prompt To Page plugin
document.addEventListener('DOMContentLoaded', function() {
    // Check if API key is configured
    const apiKeyConfigured = typeof ptp_chat_ajax !== 'undefined' && ptp_chat_ajax.api_key_configured;
    
    // Get the container element
    const container = document.getElementById('ptp-chat-container');
    
    // If no API key is configured, show setup message instead of chat
    if (!apiKeyConfigured) {
        const setupMessage = document.createElement('div');
        setupMessage.className = 'ptp-no-api-key-message';
        setupMessage.innerHTML = `
            <p>You must configure an API key to get started.</p>
            <a href="${ptp_chat_ajax.settings_url}" class="button button-primary">Go to Settings</a>
        `;
        container.appendChild(setupMessage);
        return;
    }
    
    // Create chat UI elements
    const chatContainer = document.createElement('div');
    chatContainer.className = 'ptp-chat-container';
    
    // Create messages display area
    const messagesDiv = document.createElement('div');
    messagesDiv.className = 'ptp-messages';
    
    // Create input area
    const inputArea = document.createElement('div');
    inputArea.className = 'ptp-input-area';
    
    // Create text input
    const messageInput = document.createElement('input');
    messageInput.type = 'text';
    messageInput.className = 'ptp-message-input';
    messageInput.placeholder = 'Enter your prompt...';
    
    // Create send button
    const sendButton = document.createElement('button');
    sendButton.className = 'ptp-send-button';
    sendButton.textContent = 'Send';
    
    // Create create page button
    const createPageButton = document.createElement('button');
    createPageButton.className = 'ptp-create-page-button';
    createPageButton.textContent = 'Create Page';
    
    // Append elements to input area
    inputArea.appendChild(messageInput);
    inputArea.appendChild(sendButton);
    inputArea.appendChild(createPageButton);
    
    // Append elements to chat container
    chatContainer.appendChild(messagesDiv);
    chatContainer.appendChild(inputArea);
    
    // Add to DOM
    container.appendChild(chatContainer);
    
    // Store messages in memory
    let messages = [];
    
    // Function to add a message to the UI
    function addMessageToUI(role, content) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `ptp-message ptp-message-${role}`;
        
        const messageContent = document.createElement('div');
        messageContent.className = 'ptp-message-content';
        messageContent.textContent = content;
        
        messageDiv.appendChild(messageContent);
        messagesDiv.appendChild(messageDiv);
        
        // Scroll to bottom
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
    }
    
    // Function to handle sending a message
    function sendMessage() {
        const message = messageInput.value.trim();
        if (message === '') return;
        
        // Add user message to UI
        addMessageToUI('user', message);
        
        // Add to messages array
        messages.push({
            role: 'user',
            content: message
        });
        
        // Clear input
        messageInput.value = '';
        
        // Simulate AI response (in a real implementation, this would be an API call)
        setTimeout(() => {
            const aiResponse = `I received your prompt: "${message}". This is a simulated AI response.`;
            addMessageToUI('assistant', aiResponse);
            
            messages.push({
                role: 'assistant',
                content: aiResponse
            });
        }, 500);
    }
    
    // Event listeners
    sendButton.addEventListener('click', sendMessage);
    
    messageInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendMessage();
        }
    });
    
    // Function to create a page from the conversation
    async function createPage() {
        if (messages.length === 0) return;
        
        // Get the last user message as title
        const lastUserMessage = messages.filter(msg => msg.role === 'user').pop();
        const title = lastUserMessage ? lastUserMessage.content : 'AI Draft';
        
        try {
            // Send POST request to REST endpoint
            const response = await fetch(ptp_chat_ajax.rest_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': ptp_chat_ajax.nonce
                },
                body: JSON.stringify({
                    messages: messages,
                    title: title
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Display success message with link to draft
                const successDiv = document.createElement('div');
                successDiv.className = 'ptp-success-message';
                successDiv.innerHTML = `
                    <p>Page created successfully!</p>
                    <a href="${result.preview_url}" target="_blank">View Draft</a>
                `;
                messagesDiv.appendChild(successDiv);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            } else {
                // Display error message
                const errorDiv = document.createElement('div');
                errorDiv.className = 'ptp-error-message';
                errorDiv.textContent = `Error: ${result.message || 'Failed to create page'}`;
                messagesDiv.appendChild(errorDiv);
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        } catch (error) {
            // Display error message
            const errorDiv = document.createElement('div');
            errorDiv.className = 'ptp-error-message';
            errorDiv.textContent = `Error: ${error.message || 'Failed to create page'}`;
            messagesDiv.appendChild(errorDiv);
            messagesDiv.scrollTop = messagesDiv.scrollHeight;
        }
    }
    
    // Event listener for Create Page button
    createPageButton.addEventListener('click', createPage);
});