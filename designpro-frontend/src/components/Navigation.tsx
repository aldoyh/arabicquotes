import { useState } from 'react';
import { Menu, X, ArrowRight } from 'lucide-react';

export const Navigation = () => {
  const [isOpen, setIsOpen] = useState(false);

  const navLinks = [
    'Home',
    'About Us',
    'Courses',
    'Instructors',
    'Testimonials',
    'Blog',
  ];

  return (
    <nav className="fixed top-0 left-0 right-0 z-50 bg-black/80 backdrop-blur-sm border-b border-white/10">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="flex justify-between items-center h-20">
          {/* Logo */}
          <div className="flex items-center gap-2">
            <div className="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center">
              <div className="w-4 h-4 rounded-full bg-white" />
            </div>
            <span className="text-white font-medium text-sm">DesignPro</span>
          </div>

          {/* Desktop Navigation */}
          <div className="hidden lg:flex">
            <div className="flex items-center gap-8 px-6 py-2 bg-gray-900/50 rounded-full border border-gray-700">
              {navLinks.map((link) => (
                <a
                  key={link}
                  href="#"
                  className="text-white/80 text-sm hover:text-white transition-colors"
                >
                  {link}
                </a>
              ))}
              <button className="flex items-center gap-2 text-white/80 hover:text-white transition-colors text-sm group">
                Contact us
                <ArrowRight className="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </button>
            </div>
          </div>

          {/* Mobile menu button */}
          <button
            onClick={() => setIsOpen(!isOpen)}
            className="lg:hidden p-2 text-white/80 hover:text-white"
          >
            {isOpen ? (
              <X className="w-6 h-6" />
            ) : (
              <Menu className="w-6 h-6" />
            )}
          </button>
        </div>

        {/* Mobile Navigation */}
        {isOpen && (
          <div className="lg:hidden pb-4 space-y-2">
            {navLinks.map((link) => (
              <a
                key={link}
                href="#"
                className="block text-white/80 text-sm hover:text-white transition-colors py-2"
              >
                {link}
              </a>
            ))}
            <button className="flex items-center gap-2 text-white/80 hover:text-white transition-colors text-sm w-full py-2">
              Contact us
              <ArrowRight className="w-4 h-4" />
            </button>
          </div>
        )}
      </div>
    </nav>
  );
};
