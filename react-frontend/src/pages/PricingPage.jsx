import { Link } from 'react-router-dom';
import { useUser } from '../contexts/UserContext';

const PricingPage = () => {
  const { user } = useUser();

  return (
    <div className="max-w-7xl mx-auto py-8 px-4">
      {/* Page Header */}
      <div className="text-center mb-12">
        <h1 className="text-4xl font-bold mb-4 text-gray-900 dark:text-white">
          <i className="fas fa-rocket mr-3 text-blue-500"></i>
          Upgrade Your PasteForge Experience
        </h1>
        <p className="text-lg text-gray-600 dark:text-gray-400 max-w-2xl mx-auto">
          Choose the perfect plan to enhance your code sharing and collaboration experience
        </p>
      </div>

      {/* Pricing Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-12">
        {/* Free Plan */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
          <div className="text-center mb-6">
            <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Free</h3>
            <div className="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
              $0
              <span className="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">Basic features for casual users</p>
          </div>

          <ul className="space-y-3 mb-8">
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Create & share pastes</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Syntax highlighting</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Public pastes</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Self-destruct option</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Basic syntax highlighting</span>
            </li>
            <li className="flex items-center text-sm text-gray-400">
              <i className="fas fa-times text-red-500 mr-3"></i>
              <span>Analytics</span>
            </li>
          </ul>

          <button className="w-full py-2 px-4 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
            Current Plan
          </button>
        </div>

        {/* Starter AI Plan */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
          <div className="text-center mb-6">
            <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Starter AI</h3>
            <div className="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
              $5
              <span className="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">Essential AI features for knowledge workers</p>
          </div>

          <ul className="space-y-3 mb-8">
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Everything in Free</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>AI Generated Tags</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>AI Tag Suggestions (3/hr)</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>AI Search Multibot</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Private pastes</span>
            </li>
            <li className="flex items-center text-sm text-gray-400">
              <i className="fas fa-times text-red-500 mr-3"></i>
              <span>Advanced AI features</span>
            </li>
          </ul>

          <button className="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i className="fas fa-rocket mr-2"></i>
            Select Plan
          </button>
        </div>

        {/* Pro AI Plan */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border-2 border-blue-500 p-6 relative">
          {/* Popular Badge */}
          <div className="absolute -top-3 left-1/2 transform -translate-x-1/2">
            <span className="bg-blue-500 text-white px-4 py-1 rounded-full text-sm font-medium">Popular</span>
          </div>

          <div className="text-center mb-6">
            <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Pro AI</h3>
            <div className="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
              $10
              <span className="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">Complete features for power users</p>
          </div>

          <ul className="space-y-3 mb-8">
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Full AI Code Refactoring</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Advanced AI models</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Unlimited AI queries</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Scheduled Publishing</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Private Collections</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Priority Support</span>
            </li>
          </ul>

          <button className="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i className="fas fa-crown mr-2"></i>
            Select Plan
          </button>
        </div>

        {/* Dev Team Plan */}
        <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 p-6 relative">
          <div className="text-center mb-6">
            <h3 className="text-xl font-semibold mb-2 text-gray-900 dark:text-white">Dev Team</h3>
            <div className="text-3xl font-bold mb-2 text-gray-900 dark:text-white">
              $25
              <span className="text-sm font-normal text-gray-500">/month</span>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400">Collaborative tools for development teams</p>
          </div>

          <ul className="space-y-3 mb-8">
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Use Collaborative Tools</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Team chat sharing (5 users)</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>Advanced Analytics</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>SSO & ACLs</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>API Webhooks</span>
              <span className="ml-2 text-xs bg-gray-200 dark:bg-gray-600 px-2 py-1 rounded">coming soon</span>
            </li>
            <li className="flex items-center text-sm">
              <i className="fas fa-check text-green-500 mr-3"></i>
              <span>All Pro AI features</span>
            </li>
          </ul>

          <button className="w-full py-2 px-4 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
            <i className="fas fa-users mr-2"></i>
            Select Plan
          </button>
        </div>
      </div>

      {/* Information Banner */}
      <div className="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-700 rounded-lg p-4 mb-8">
        <div className="flex items-center justify-center">
          <i className="fas fa-info-circle text-blue-500 mr-3"></i>
          <span className="text-blue-800 dark:text-blue-200 text-sm">
            This is a development environment web interface preview. In production, you would be redirected to a payment processor.
          </span>
        </div>
      </div>

      {/* Features Comparison */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8 mb-8">
        <h2 className="text-2xl font-bold text-center mb-8 text-gray-900 dark:text-white">
          Feature Comparison
        </h2>

        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="border-b border-gray-200 dark:border-gray-700">
                <th className="text-left py-4 px-4 font-medium text-gray-900 dark:text-white">Feature</th>
                <th className="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Free</th>
                <th className="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Starter AI</th>
                <th className="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Pro AI</th>
                <th className="text-center py-4 px-4 font-medium text-gray-900 dark:text-white">Dev Team</th>
              </tr>
            </thead>
            <tbody className="text-sm">
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <td className="py-4 px-4">Public Pastes</td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
              </tr>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <td className="py-4 px-4">Private Pastes</td>
                <td className="text-center py-4 px-4"><span className="text-gray-400">Limited</span></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
              </tr>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <td className="py-4 px-4">AI Code Analysis</td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><span className="text-blue-600">Basic</span></td>
                <td className="text-center py-4 px-4"><span className="text-green-600">Advanced</span></td>
                <td className="text-center py-4 px-4"><span className="text-green-600">Advanced</span></td>
              </tr>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <td className="py-4 px-4">Team Collaboration</td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><span className="text-blue-600">Basic</span></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
              </tr>
              <tr className="border-b border-gray-100 dark:border-gray-700">
                <td className="py-4 px-4">API Access</td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><span className="text-blue-600">Limited</span></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
              </tr>
              <tr>
                <td className="py-4 px-4">Priority Support</td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-times text-red-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
                <td className="text-center py-4 px-4"><i className="fas fa-check text-green-500"></i></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      {/* FAQ Section */}
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-8">
        <h2 className="text-2xl font-bold text-center mb-8 text-gray-900 dark:text-white">
          Frequently Asked Questions
        </h2>

        <div className="grid md:grid-cols-2 gap-8">
          <div>
            <h3 className="font-semibold mb-2 text-gray-900 dark:text-white">Can I change my plan at any time?</h3>
            <p className="text-gray-600 dark:text-gray-400 text-sm">
              Yes, you can upgrade or downgrade your plan at any time. Changes take effect immediately.
            </p>
          </div>

          <div>
            <h3 className="font-semibold mb-2 text-gray-900 dark:text-white">What payment methods do you accept?</h3>
            <p className="text-gray-600 dark:text-gray-400 text-sm">
              We accept all major credit cards and PayPal for subscription payments.
            </p>
          </div>

          <div>
            <h3 className="font-semibold mb-2 text-gray-900 dark:text-white">Is there a free trial for paid plans?</h3>
            <p className="text-gray-600 dark:text-gray-400 text-sm">
              Yes, we offer a 7-day free trial for all paid plans. No credit card required.
            </p>
          </div>

          <div>
            <h3 className="font-semibold mb-2 text-gray-900 dark:text-white">Can I cancel my subscription?</h3>
            <p className="text-gray-600 dark:text-gray-400 text-sm">
              Absolutely. You can cancel your subscription at any time from your account settings.
            </p>
          </div>
        </div>
      </div>

      {/* Call to Action */}
      <div className="text-center mt-12">
        <div className="bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg p-8 text-white">
          <h2 className="text-3xl font-bold mb-4">Ready to Get Started?</h2>
          <p className="text-lg mb-6 opacity-90">
            Join thousands of developers who trust PasteForge for their code sharing needs
          </p>
          {!user ? (
            <div className="space-x-4">
              <Link to="/signup" className="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
                <i className="fas fa-user-plus mr-2"></i>
                Start Free Trial
              </Link>
              <Link to="/login" className="inline-block border border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-blue-600 transition-colors">
                <i className="fas fa-sign-in-alt mr-2"></i>
                Sign In
              </Link>
            </div>
          ) : (
            <Link to="/account" className="inline-block bg-white text-blue-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition-colors">
              <i className="fas fa-arrow-left mr-2"></i>
              Back to Account
            </Link>
          )}
        </div>
      </div>
    </div>
  );
};

export default PricingPage;