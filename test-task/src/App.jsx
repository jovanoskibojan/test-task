import { useState, useRef } from 'react';
import './App.css';

function App() {
    const [error, setError] = useState('');
    const [success, setSuccess] = useState('');
    const [loading, setLoading] = useState(false);

    const inputRef = useRef(null); // reference to input

    const handleSubmit = (e) => {
        e.preventDefault();
        if (loading) return;
        const inputValue = inputRef.current.value.trim();
        if (!inputValue) return;

        // Hide previous messages
        setError('');
        setSuccess('');

        setLoading(true); // disable button and show waiting text

        fetch('https://test.jovanoskibojan.com/api/index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ url: inputValue })
        })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'error') {
                    setError(data.message);
                } else {
                    setSuccess('Form submitted successfully!');
                }
            })
            .catch(() => {
                setError('Server error');
            })
            .finally(() => {
                setLoading(false);           // re-enable button
                inputRef.current.value = ''; // clear input
                inputRef.current.focus();    // focus input again
            });
    };

    return (
        <div className="wrapper">
            <label htmlFor="document-url">Enter document URL:</label>
            <input id="document-url" type="text" placeholder="Enter here..." ref={inputRef} />
            <button onClick={handleSubmit} disabled={loading}>
                {loading ? 'Please wait...' : 'Submit'}
            </button>

            {/* Show messages conditionally */}
            {error && <div className="message error">{error}</div>}
            {success && <div className="message success">{success}</div>}
        </div>
    );
}

export default App;
