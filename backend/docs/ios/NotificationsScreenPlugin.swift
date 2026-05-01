import Capacitor
import UIKit
import WebKit

// MARK: - Plugin

@objc(NotificationsScreenPlugin)
public class NotificationsScreenPlugin: CAPPlugin, CAPBridgedPlugin {

    public let identifier = "NotificationsScreenPlugin"
    public let jsName = "NotificationsScreen"
    public let pluginMethods: [CAPPluginMethod] = [
        CAPPluginMethod(name: "open", returnType: CAPPluginReturnPromise),
    ]

    @objc func open(_ call: CAPPluginCall) {
        DispatchQueue.main.async { [weak self] in
            guard let self = self, let bridge = self.bridge else {
                call.reject("Bridge unavailable")
                return
            }

            let vc = NotificationsViewController()
            // Pass WKWebView so the VC can extract cookies for authenticated API calls
            vc.webView = bridge.webView

            let nav = UINavigationController(rootViewController: vc)
            nav.modalPresentationStyle = .pageSheet
            if let sheet = nav.sheetPresentationController {
                sheet.detents = [.large()]
                sheet.prefersGrabberVisible = true
            }

            bridge.viewController?.present(nav, animated: true) {
                call.resolve()
            }
        }
    }
}
