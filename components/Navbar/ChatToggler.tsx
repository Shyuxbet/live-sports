import {useEffect, useState} from "react";
import './styles/navbarStyle.css'
import TagManager from 'react-gtm-module'

// Anti ESA na live do Baiano
export function ChatToggler() {
    const [toggled, setToggled] = useState(false);

    useEffect(() => {
        const chatData = localStorage.getItem("chat");
        if(chatData) {
            if (chatData === "mute") {
                setToggled(false);
            } else if (chatData === "unmute") {
                setToggled(true)
            }
        }
    }, [])

    const handleClick = () => {
        if(toggled) {
            localStorage.setItem("chat", "mute");
            TagManager.dataLayer({ dataLayer: { event: 'chat_toggled', action: 'muted'} })
        }else{
            localStorage.setItem("chat", "unmute");
            TagManager.dataLayer({ dataLayer: { event: 'chat_toggled', action: 'unmuted' } })
        }

        let videoPlayer = document.querySelector(`#video-player`)
        if (videoPlayer) {
            videoPlayer.removeAttribute(`added`)
        }

        setToggled((s) => !s);
    }

    return (
        <div className="toggle-container">
            <div onClick={handleClick} className={`chat-toggle${toggled ? " muted" : ""}`}>
                <div className="notch">{`${toggled ? "✅" : "❌"}`}</div>
            </div>
        </div>
    );
}